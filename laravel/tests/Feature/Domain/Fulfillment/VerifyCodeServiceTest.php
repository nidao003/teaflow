<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Fulfillment;

use App\Domain\Fulfillment\Enums\VerifyCodeStatus;
use App\Domain\Fulfillment\Enums\VerifyCodeType;
use App\Domain\Fulfillment\Exceptions\CrossStoreOperationException;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Exceptions\InvalidStateTransitionException;
use App\Domain\Fulfillment\Exceptions\VerifyCodeExpiredException;
use App\Domain\Fulfillment\Exceptions\VerifyCodeNotFoundException;
use App\Domain\Fulfillment\Exceptions\VerifyCodeUsedException;
use App\Domain\Fulfillment\Models\VerifyCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VerifyCodeServiceTest extends FulfillmentTestCase
{
    public function test_issue_creates_code_with_defaults(): void
    {
        $code = $this->issueCode();

        $this->assertStringStartsWith('VC', $code->code);
        $this->assertSame('ORDER-1', $code->order_number);
        $this->assertSame(1, $code->store_id);
        $this->assertSame(VerifyCodeType::PICKUP, $code->type);
        $this->assertSame(VerifyCodeStatus::ISSUED, $code->status);
        $this->assertNotNull($code->expires_at);
        $this->assertTrue($code->expires_at->isFuture());
        $this->assertTrue($code->isUsable());
    }

    public function test_issue_rejects_past_expires_at(): void
    {
        $this->expectException(FulfillmentException::class);

        $this->issueCode(['expires_at' => Carbon::now()->subMinute()]);
    }

    public function test_use_consumes_code_exactly_once(): void
    {
        $code = $this->issueCode();

        $used = $this->verifyCodes()->use($code->code, 1, 7, 'ORDER-1');

        $this->assertSame(VerifyCodeStatus::USED, $used->status);
        $this->assertNotNull($used->used_at);
        $this->assertSame(7, $used->used_by);
        $this->assertFalse($used->isUsable());

        // 重复核销只成功一次：第二次必须拒绝，且不改变首次核销的时间
        try {
            $this->verifyCodes()->use($code->code, 1, 8, 'ORDER-1');
            $this->fail('重复核销未被拒绝');
        } catch (VerifyCodeUsedException) {
            // expected
        }

        $fresh = $code->fresh();
        $this->assertSame(VerifyCodeStatus::USED, $fresh->status);
        $this->assertSame(7, $fresh->used_by);
        $this->assertSame($used->used_at?->toDateTimeString(), $fresh->used_at?->toDateTimeString());
    }

    public function test_use_rejects_cross_store(): void
    {
        $code = $this->issueCode(['store_id' => 1]);

        try {
            $this->verifyCodes()->use($code->code, 2);
            $this->fail('跨店核销未被拒绝');
        } catch (CrossStoreOperationException $exception) {
            $this->assertStringContainsString('不属于当前门店', $exception->getMessage());
        }

        // 跨店拒绝后码仍可用，且只能在发码门店使用
        $this->assertSame(VerifyCodeStatus::ISSUED, $code->fresh()->status);
        $this->assertNull($code->fresh()->used_at);

        $used = $this->verifyCodes()->use($code->code, 1);
        $this->assertSame(VerifyCodeStatus::USED, $used->status);
    }

    public function test_use_rejects_expired_code(): void
    {
        $expired = VerifyCode::create([
            'code' => 'VC-EXPIRED-001',
            'order_number' => 'ORDER-1',
            'store_id' => 1,
            'type' => VerifyCodeType::PICKUP,
            'status' => VerifyCodeStatus::ISSUED,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        try {
            $this->verifyCodes()->use('VC-EXPIRED-001', 1);
            $this->fail('过期核销码未被拒绝');
        } catch (VerifyCodeExpiredException) {
            // expected
        }

        $fresh = $expired->fresh();
        $this->assertSame(VerifyCodeStatus::EXPIRED, $fresh->status);
        $this->assertNull($fresh->used_at);
    }

    public function test_use_rejects_code_already_marked_expired(): void
    {
        $code = $this->issueCode();
        $this->verifyCodes()->expire($code);

        $this->expectException(VerifyCodeExpiredException::class);
        $this->verifyCodes()->use($code->code, 1);
    }

    public function test_use_rejects_void_code(): void
    {
        $code = $this->issueCode();
        $voided = $this->verifyCodes()->void($code);
        $this->assertSame(VerifyCodeStatus::VOID, $voided->status);

        $this->expectException(FulfillmentException::class);
        $this->verifyCodes()->use($code->code, 1);
    }

    public function test_use_rejects_order_number_mismatch(): void
    {
        $code = $this->issueCode(['order_number' => 'ORDER-1']);

        try {
            $this->verifyCodes()->use($code->code, 1, null, 'ORDER-2');
            $this->fail('订单不匹配未拒绝');
        } catch (FulfillmentException $exception) {
            $this->assertStringContainsString('与订单不匹配', $exception->getMessage());
        }

        $this->assertSame(VerifyCodeStatus::ISSUED, $code->fresh()->status);
    }

    public function test_unknown_code_is_rejected(): void
    {
        $this->expectException(VerifyCodeNotFoundException::class);

        $this->verifyCodes()->use('VC-NOT-EXIST', 1);
    }

    public function test_use_after_external_usage_is_rejected(): void
    {
        $code = $this->issueCode();

        // 模拟并发场景中另一个请求已核销成功的提交
        DB::table('verify_codes')->where('id', $code->id)->update([
            'status' => VerifyCodeStatus::USED->value,
            'used_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->expectException(VerifyCodeUsedException::class);
        $this->verifyCodes()->use($code->code, 1);
    }

    public function test_expire_and_expire_due(): void
    {
        $expiredByExpire = $this->issueCode(['order_number' => 'ORDER-A']);
        $this->assertSame(VerifyCodeStatus::EXPIRED, $this->verifyCodes()->expire($expiredByExpire)->status);
        // 重复 expire 幂等
        $this->assertSame(VerifyCodeStatus::EXPIRED, $this->verifyCodes()->expire($expiredByExpire)->status);

        VerifyCode::create([
            'code' => 'VC-DUE-001',
            'order_number' => 'ORDER-B',
            'store_id' => 1,
            'type' => VerifyCodeType::PICKUP,
            'status' => VerifyCodeStatus::ISSUED,
            'expires_at' => Carbon::now()->subHour(),
        ]);
        $future = VerifyCode::create([
            'code' => 'VC-FUTURE-001',
            'order_number' => 'ORDER-C',
            'store_id' => 1,
            'type' => VerifyCodeType::PICKUP,
            'status' => VerifyCodeStatus::ISSUED,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $affected = $this->verifyCodes()->expireDue();

        $this->assertSame(1, $affected);
        $this->assertSame(VerifyCodeStatus::EXPIRED, VerifyCode::query()->where('code', 'VC-DUE-001')->firstOrFail()->status);
        $this->assertSame(VerifyCodeStatus::ISSUED, $future->fresh()->status);
    }

    public function test_void_used_code_is_rejected(): void
    {
        $code = $this->issueCode();
        $this->verifyCodes()->use($code->code, 1);

        $this->expectException(InvalidStateTransitionException::class);
        $this->verifyCodes()->void($code->fresh());
    }

    public function test_expire_used_code_is_rejected(): void
    {
        $code = $this->issueCode();
        $this->verifyCodes()->use($code->code, 1);

        $this->expectException(InvalidStateTransitionException::class);
        $this->verifyCodes()->expire($code->fresh());
    }
}
