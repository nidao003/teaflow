<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ArticleType;
use App\Models\Article;

class ArticleSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (ArticleType::count() == 0) {
            ArticleType::create([
                'title' => '协议',
            ]);
            Article::create([
                'title' => '会员章程',
                'type_id' => 1,
                'body' => '<h1 style="text-align: start; line-height: 1.5;"><strong>会员章程</strong></h1><p style="text-align: start;"><strong>生效日期：</strong> [填写日期]<br><strong>版本号：</strong> [填写版本号]</p><p style="text-align: start;">欢迎注册成为[小程序/平台名称]（以下简称“本平台”）的会员！本章程旨在明确会员权利与义务，规范会员行为，保障双方合法权益。请仔细阅读并同意后使用会员服务。</p><h2 style="text-align: start; line-height: 1.5;"><strong>第一章 会员资格</strong></h2><ol><li style="text-align: start;">注册条件年满14周岁并具备完全民事行为能力的自然人，或合法设立的企业/组织。需通过本平台指定的方式（如手机号、微信授权等）完成实名认证。</li><li style="text-align: start;">会员类型普通会员：完成基础注册即可享有基础权益。付费会员：通过购买会员服务升级，享有专属特权（如折扣、优先客服等）。</li><li style="text-align: start;">禁止行为冒用他人身份或提供虚假信息注册。将会员账号转让、出租或用于非法用途。</li></ol><h2 style="text-align: start; line-height: 1.5;"><strong>第二章 会员权利</strong></h2><ol><li style="text-align: start;">基础权利使用本平台提供的会员专属功能与服务。参与会员专属活动（如积分兑换、优惠促销等）。</li><li style="text-align: start;">等级权益根据会员成长值/消费累计提升等级，享受对应特权（如更高折扣、生日礼包等）。等级规则详见《会员等级说明》（附链接）。</li><li style="text-align: start;">服务保障对会员服务问题提出咨询或投诉，平台需在[填写时限]内响应。</li></ol><h2 style="text-align: start; line-height: 1.5;"><strong>第三章 会员义务</strong></h2><ol><li style="text-align: start;">信息真实性确保注册信息及后续提交的内容真实、完整，如有变更需及时更新。</li><li style="text-align: start;">行为规范不得利用会员身份从事违法活动、发布违规内容或干扰平台运营。遵守本平台发布的全部规则及国家法律法规。</li><li style="text-align: start;">账号安全妥善保管账号及密码，因个人泄露导致损失需自行承担责任。</li></ol><h2 style="text-align: start; line-height: 1.5;"><strong>第四章 会员服务规则</strong></h2><ol><li style="text-align: start;">服务内容会员专享折扣、优先客服、免费课程等（根据实际权益填写）。具体服务以平台公示或协议约定为准。</li><li style="text-align: start;">积分与奖励积分通过签到、消费、参与活动等方式获取，可用于兑换礼品或抵扣费用。积分有效期、使用规则详见《积分管理规则》（附链接）。</li><li style="text-align: start;">服务变更与终止平台有权根据运营需要调整会员权益，并提前[填写天数]天公告。会员主动注销账号或违反章程的，平台可终止服务且不退还已付费费用。</li></ol><h2 style="text-align: start; line-height: 1.5;"><strong>第五章 隐私与数据保护</strong></h2><ol><li style="text-align: start;">会员个人信息处理遵循《隐私政策》（附链接），未经同意不向第三方披露。</li><li style="text-align: start;">平台有权基于服务优化或安全目的收集使用行为数据（如登录记录、交易信息）。</li></ol><h2 style="text-align: start; line-height: 1.5;"><strong>第六章 免责声明</strong></h2><ol><li style="text-align: start;">因不可抗力（如网络故障、自然灾害）导致服务中断的，平台不承担责任。</li><li style="text-align: start;">会员因违规行为造成的损失由会员自行承担。</li></ol><h2 style="text-align: start; line-height: 1.5;"><strong>第七章 附则</strong></h2><ol><li style="text-align: start;">本章程修改将通过公告、站内信等方式通知，修改后内容自动生效。</li><li style="text-align: start;">争议解决：如发生纠纷，双方应协商解决；协商不成可向[填写管辖法院]提起诉讼。</li></ol><p style="text-align: start;"><strong>联系我们</strong><br>如对本章程有疑问，请通过以下方式联系：</p><ul><li style="text-align: start;">客服电话：[填写电话]</li><li style="text-align: start;">邮箱：[填写邮箱]</li><li style="text-align: start;">在线客服：[平台内入口]</li></ul>',
            ]);
            Article::create([
                'title' => '隐私政策',
                'type_id' => 1,
                'body' => '<h1 style="text-align: start; line-height: 1.5;"><strong>小程序隐私政策</strong></h1><p style="text-align: start;"><strong>更新日期：</strong> [填写日期]<br><strong>生效日期：</strong> [填写日期]</p><p style="text-align: start;">本政策适用于[小程序名称]（以下简称“本小程序”）。我们深知个人信息的重要性，并承诺按照法律法规要求保护用户隐私。请在使用前仔细阅读本政策。</p><h2 style="text-align: start; line-height: 1.5;"><strong>一、我们收集的信息</strong></h2><h3 style="text-align: start; line-height: 1.5;">1. 基本功能所需信息</h3><ul><li style="text-align: start;">账号信息：当您使用微信登录时，我们会获取您的微信头像、昵称、OpenID等基本信息。</li><li style="text-align: start;">设备信息：包括设备型号、操作系统、唯一设备标识符（如IMEI）、IP地址、网络类型等。</li><li style="text-align: start;">日志信息：操作记录、访问时间、服务故障日志等。</li></ul><h3 style="text-align: start; line-height: 1.5;">2. 第三方SDK信息</h3><p style="text-align: start;">为保障功能实现，我们可能接入第三方服务商SDK（如微信支付、数据统计等），其可能收集以下信息：</p><ul><li style="text-align: start;">位置信息（需授权）：基于GPS或IP地址提供定位服务。</li><li style="text-align: start;">存储权限：用于本地缓存数据或文件上传。</li><li style="text-align: start;">其他信息：具体以第三方隐私政策为准。</li></ul><h3 style="text-align: start; line-height: 1.5;">3. 用户主动提交的信息</h3><ul><li style="text-align: start;">您在使用时主动填写的内容（如昵称、联系方式、上传的图片/文件等）。</li></ul><h2 style="text-align: start; line-height: 1.5;"><strong>二、信息的使用方式</strong></h2><p style="text-align: start;">我们仅在以下用途使用您的信息：</p><ol><li style="text-align: start;">提供账号注册、登录及基础服务。</li><li style="text-align: start;">实现小程序功能（如定位、支付、内容分享等）。</li><li style="text-align: start;">优化产品体验，分析用户行为以改进服务。</li><li style="text-align: start;">防范安全风险，如账号异常登录检测。</li></ol><h2 style="text-align: start; line-height: 1.5;"><strong>三、信息的存储与保护</strong></h2><ul><li style="text-align: start;">存储地点：在中华人民共和国境内通过安全加密方式存储。</li><li style="text-align: start;">保护措施：采用技术手段（如SSL加密、访问控制）和管理制度保障数据安全。</li><li style="text-align: start;">保留时间：在实现本政策所述目的的必要期限内保留，超出期限将匿名化或删除。</li></ul><h2 style="text-align: start; line-height: 1.5;"><strong>四、信息的共享与披露</strong></h2><ul><li style="text-align: start;">原则上不共享：未经您同意，我们不会向第三方提供个人信息，法律法规或监管部门要求的除外。</li><li style="text-align: start;">特定情况：如发生合并、收购等，您的信息可能被转移，我们将提前通知。</li></ul><h2 style="text-align: start; line-height: 1.5;"><strong>五、您的权利</strong></h2><ol><li style="text-align: start;">访问与更正：通过小程序内设置或联系客服查阅、修改个人信息。</li><li style="text-align: start;">删除信息：注销账号后，我们将删除或匿名化您的信息（法律要求保留的除外）。</li><li style="text-align: start;">撤回授权：通过设备权限设置关闭地理位置、相机等权限（可能影响部分功能）。</li></ol><h2 style="text-align: start; line-height: 1.5;"><strong>六、未成年人保护</strong></h2><p style="text-align: start;">若您是未满14周岁的未成年人，请在监护人指导下使用本小程序。我们不会在知情情况下收集未成年人信息。</p><h2 style="text-align: start; line-height: 1.5;"><strong>七、政策更新</strong></h2><p style="text-align: start;">我们可能修订本政策，更新版本会通过公告或弹窗提示。若您继续使用视为接受修改后的政策。</p><h2 style="text-align: start; line-height: 1.5;"><strong>八、联系我们</strong></h2><p style="text-align: start;">如对政策有疑问或需行使权利，请通过以下方式联系：</p><ul><li style="text-align: start;">邮箱：[填写客服邮箱]</li><li style="text-align: start;">在线客服：[小程序内客服入口]</li></ul>',
            ]);

        }
    }
}
