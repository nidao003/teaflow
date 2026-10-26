# TeaFlow

**茶饮门店经营系统（多店版）· 开发中**

TeaFlow 面向多门店茶饮连锁场景，目标是一套数据自有、可持续二次开发的经营系统：小程序点单、门店后台、POS 收银、商品/配方、库存/采购、会员/营销、经营分析与预警。

> 当前基于开源项目 [cameasy/liketea](https://github.com/cameasy/liketea)（MIT）的 basic 版启动，处于**开发准备阶段，不是生产可用版本**。

---

## 当前状态

| 阶段 | 状态 |
|---|---|
| 方案与代码底座审计 | ✅ 已完成（审计报告、问题清单、12 域建设来源矩阵、功能清单） |
| G0 契约与建模 | 🚧 进行中：表/字段冻结、API/状态机/权限契约、第三方组件登记、指标字典 V1.1 |
| G1 安全与升级 | ⏳ 待开始：10 个安全 P0、Laravel 12/13 + PHP 8.3、CI |
| G2 交易核心 | ⏳ 待开始：商品/订单/支付/退款/打印/券/自提 + POS 最小闭环 |
| G3–G6 | ⏳ 多店/会员 → 库存/配方 → 数据/BI → 运营/财务/开放 |

> **已知限制**：上游 basic 版缺少库存/采购/配方 BOM/权限/发票/财务等模块；部分页面为壳或假实现；存在 10 个安全 P0；Laravel 10 / PHP 8.1 已 EOL。上线前必须完成 G1。

---

## 技术栈

| 层 | 技术 |
|---|---|
| 后端 | Laravel 10（计划升级 12/13）、PHP 8.1（目标 8.3）、MySQL 8、Redis（可选） |
| 实时/队列 | Laravel Octane、Reverb、Queue |
| 管理后台 | Vue 3 + TypeScript + Element Plus + Vite |
| 小程序/H5 | uni-app（HBuilderX 工程） |
| 支付 | 微信支付 / 支付宝（后续自建收单与 POS） |
| 打印 | 飞鹅云打印（后续扩展 ESC/POS、标签、钱箱） |
| 数据 | MySQL 业务库 + 分析层（DWD/DWS/ADS）+ Superset 或自研看板 |

---

## 目录结构

```text
teaflow/
├── laravel/          # Laravel 后端（API、支付、打印、后台逻辑）
├── admin/            # Vue3 管理后台（平台 + 商家/门店）
├── uniapp/           # 小程序 / H5
├── README.md
└── LICENSE
```

---

## 本地开发

### 环境要求

- PHP 8.1+（**目标 8.3+**；8.1 已 EOL）
- Composer 2
- MySQL 8（本地可用 Docker）
- Node.js 18+ / npm
- HBuilderX（小程序端，或用其 CLI）

### 后端

```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
# 修改 .env 中的数据库、支付、打印配置
php artisan migrate --seed
php artisan serve
```

> ⚠️ `.env.example` 中的密钥/口令仅为占位，**必须替换**；`UserSeeder` 会创建默认管理员，首次登录后立即改密。

### 管理后台

```bash
cd admin
npm install
npm run dev
```

### 小程序 / H5

用 HBuilderX 导入 `uniapp/` 运行；或使用 HBuilderX CLI 构建。

---

## 功能范围（目标）

- **多店/组织**：公司 → 区域 → 门店、角色/权限/数据范围
- **商品/配方**：SPU/SKU、杯型/属性/加料、门店菜单、售罄/估清、配方 BOM、成本卡
- **交易**：小程序/POS/H5 下单、支付/退款、订单状态机、小票/标签
- **POS**：收银台、挂单、改价/折扣、现金/扫码/混合支付、交班/日结、钱箱、离线
- **核销/自提/预约**：核销码、取餐码、预约时段/容量、防重复
- **会员/营销**：等级/成长值、积分、储值、会员价、券、活动
- **库存/采购**：原料/仓库/批次效期、采购/收货/盘点/报损、库存流水
- **门店运营/HR**：开店收档、巡检、任务、目标、排班/考勤（后置）
- **财务/分析**：收入/成本/毛利/对账、日报月报、经营看板
- **集成/开放**：微信/支付宝、电子发票、短信/企微、Webhook/开放 API

> 详细的目标表/字段、参考来源、转换方式与验收用例，见内部《12 域建设来源矩阵》（不随本仓库分发）。

---

## 仓库策略

- `origin`：公开上游 `cameasy/liketea`，**只允许 `pull`，禁止 `push`**。
- `teaflow`：个人开发仓库，**开发提交与推送目标**。
- 本地文档、审计材料、`.claude/` 等不进入本仓库。

---

## 许可证与致谢

- 本项目基于 **MIT License** 开源；使用/分发请保留原始版权与许可声明。
- 初始底座：[cameasy/liketea](https://github.com/cameasy/liketea)（MIT，Copyright NexoPHP Software）。
- 参考/借鉴的开源项目包括：CRMEB、likeshop、TIGShop、ERPNext、Odoo、GreaterWMS、Apache Superset、RuoYi-Vue-Plus、desk-order、wechatscan 等；**GPL/LGPL/商业授权项目仅参考模型与流程，不复制代码**。第三方组件登记与许可证清单在开发过程中维护。

---

## 开发路线

| 批次 | 内容 |
|---|---|
| G0 | 架构基线、表/字段冻结、API/状态机/权限契约、第三方组件登记、指标字典 V1.1 |
| G1 | 10 个安全 P0、Laravel/PHP 升级、CI、测试骨架、Docker |
| G2 | 商品/订单/支付/退款/打印/券/自提 + POS 最小闭环 |
| G3 | 多店/组织/权限/数据范围、会员等级/积分/券 |
| G4 | 库存/采购/效期、配方 BOM/成本卡 |
| G5 | 数据/BI：DWD/DWS/ADS、指标、看板、质量 |
| G6 | 门店运营/HR、财务/经营分析、开放 API |

> 每批退出标准：契约测试 + 关键路径 E2E + 安全清单 + migration dry-run + runbook。
