<?php

declare(strict_types=1);

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Enums\InspectionItemResult;
use App\Domain\Hr\Enums\InspectionStatus;
use App\Domain\Hr\Exceptions\InspectionException;
use App\Domain\Hr\Models\Inspection;
use App\Domain\Hr\Models\InspectionItem;
use App\Domain\Hr\Support\Decimal;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 巡检服务：建单、检查项打分、提交评分、整改与关闭。
 *
 * 评分规则：提交时要求所有检查项都已评分（必填项校验）；
 * 得分 = 通过项 / 计分项 * 100（na 项不计入分母），保留两位小数；
 * 得分 >= PASS_SCORE 判为 passed，否则 failed；failed 可进入整改，整改后可关闭。
 */
class InspectionService
{
    public const PASS_SCORE = 80.00;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createInspection(array $attributes): Inspection
    {
        $storeId = $this->requirePositiveInt($attributes['store_id'] ?? null, 'store_id');
        $templateName = $this->requireString($attributes['template_name'] ?? null, '巡检模板名称', 64);
        $inspectorId = $this->optionalPositiveInt($attributes['inspector_id'] ?? null, 'inspector_id');

        $items = $attributes['items'] ?? [];
        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('巡检单至少需要一个检查项');
        }

        return DB::transaction(function () use ($storeId, $templateName, $inspectorId, $items): Inspection {
            $inspection = Inspection::query()->create([
                'store_id' => $storeId,
                'template_name' => $templateName,
                'inspector_id' => $inspectorId,
                'score' => '0.00',
                'status' => InspectionStatus::DRAFT,
                'checked_at' => null,
            ]);

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    throw new InvalidArgumentException('第 '.($index + 1).' 个检查项格式非法');
                }

                $name = $this->requireString($item['item'] ?? null, '检查项名称', 128);
                $result = $item['result'] ?? null;
                if ($result !== null) {
                    if (! is_string($result)) {
                        throw new InvalidArgumentException('检查项结果格式非法');
                    }
                    InspectionItemResult::assertValid($result);
                }

                InspectionItem::query()->create([
                    'inspection_id' => (int) $inspection->getKey(),
                    'item' => $name,
                    'result' => $result,
                    'note' => $this->optionalString($item['note'] ?? null, '检查项备注', 255),
                    'photo' => $this->optionalString($item['photo'] ?? null, '检查项照片', 255),
                ]);
            }

            return $inspection->refresh();
        });
    }

    public function addItem(
        int $inspectionId,
        string $item,
        ?string $note = null,
        ?string $photo = null
    ): InspectionItem {
        $this->findDraft($inspectionId);

        return InspectionItem::query()->create([
            'inspection_id' => $inspectionId,
            'item' => $this->requireString($item, '检查项名称', 128),
            'result' => null,
            'note' => $this->optionalString($note, '检查项备注', 255),
            'photo' => $this->optionalString($photo, '检查项照片', 255),
        ]);
    }

    public function scoreItem(int $inspectionId, int $itemId, string $result, ?string $note = null): InspectionItem
    {
        InspectionItemResult::assertValid($result);
        $this->findDraft($inspectionId);

        $item = InspectionItem::query()
            ->where('inspection_id', $inspectionId)
            ->findOrFail($itemId);

        $item->fill([
            'result' => $result,
            'note' => $note === null ? $item->note : $this->optionalString($note, '检查项备注', 255),
        ])->save();

        return $item->refresh();
    }

    public function submit(int $inspectionId, ?DateTimeInterface $now = null): Inspection
    {
        return DB::transaction(function () use ($inspectionId, $now): Inspection {
            $inspection = Inspection::query()->lockForUpdate()->findOrFail($inspectionId);

            if ($inspection->status !== InspectionStatus::DRAFT) {
                throw InspectionException::alreadySubmitted($inspectionId, (string) $inspection->status);
            }

            $items = InspectionItem::query()->where('inspection_id', $inspectionId)->get();
            if ($items->isEmpty()) {
                throw InspectionException::emptyItems($inspectionId);
            }

            foreach ($items as $item) {
                if ($item->result === null || $item->result === '') {
                    throw InspectionException::missingResult($inspectionId, (string) $item->item);
                }
            }

            $scorable = $items->where('result', '!=', InspectionItemResult::NA);
            $total = $scorable->count();
            $passed = $scorable->where('result', InspectionItemResult::PASSED)->count();
            $score = $total > 0 ? Decimal::percent($passed, $total) : '0.00';
            $status = (float) $score >= self::PASS_SCORE
                ? InspectionStatus::PASSED
                : InspectionStatus::FAILED;

            $inspection->fill([
                'score' => $score,
                'status' => $status,
                'checked_at' => $now === null ? Carbon::now() : Carbon::instance($now),
            ])->save();

            return $inspection->refresh();
        });
    }

    /**
     * 不合格巡检单进入整改。
     */
    public function rectify(int $inspectionId): Inspection
    {
        $inspection = Inspection::query()->findOrFail($inspectionId);

        if ($inspection->status !== InspectionStatus::FAILED) {
            throw InspectionException::invalidTransition(
                $inspectionId,
                (string) $inspection->status,
                InspectionStatus::RECTIFYING
            );
        }

        $inspection->fill(['status' => InspectionStatus::RECTIFYING])->save();

        return $inspection->refresh();
    }

    /**
     * 整改完成后关闭巡检单。
     */
    public function close(int $inspectionId): Inspection
    {
        $inspection = Inspection::query()->findOrFail($inspectionId);

        if ($inspection->status !== InspectionStatus::RECTIFYING) {
            throw InspectionException::invalidTransition(
                $inspectionId,
                (string) $inspection->status,
                InspectionStatus::CLOSED
            );
        }

        $inspection->fill(['status' => InspectionStatus::CLOSED])->save();

        return $inspection->refresh();
    }

    protected function findDraft(int $inspectionId): Inspection
    {
        $inspection = Inspection::query()->findOrFail($inspectionId);

        if ($inspection->status !== InspectionStatus::DRAFT) {
            throw InspectionException::invalidTransition(
                $inspectionId,
                (string) $inspection->status,
                InspectionStatus::DRAFT
            );
        }

        return $inspection;
    }

    protected function requirePositiveInt(mixed $value, string $field): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }

        $int = (int) $value;
        if ($int <= 0) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }

        return $int;
    }

    protected function optionalPositiveInt(mixed $value, string $field): ?int
    {
        return $value === null ? null : $this->requirePositiveInt($value, $field);
    }

    protected function requireString(mixed $value, string $label, int $maxLength): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException($label.'不能为空');
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException($label.'不能为空');
        }
        if (mb_strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException($label.'长度不能超过 '.$maxLength.' 个字符');
        }

        return $trimmed;
    }

    protected function optionalString(mixed $value, string $label, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->requireString($value, $label, $maxLength);
    }
}
