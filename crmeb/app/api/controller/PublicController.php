<?php

namespace app\api\controller;

use app\admin\model\system\SystemAttachment;
use app\models\store\StoreCategory;
use app\models\store\StoreCouponIssue;
use app\models\store\StoreProduct;
use app\models\store\StoreService;
use app\models\system\Express;
use app\models\user\UserBill;
use app\models\user\WechatUser;
use app\Request;
use crmeb\services\GroupDataService;
use crmeb\services\SystemConfigService;
use crmeb\services\UploadService;
use crmeb\services\UtilService;
use crmeb\services\workerman\ChannelService;
use think\facade\Cache;

/**
 * 公共类
 * Class PublicController
 * @package app\api\controller
 */
class PublicController
{
    /**
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(Request $request)
    {
        $banner = GroupDataService::getData('routine_home_banner') ?: [];//TODO 首页banner图
        $menus = GroupDataService::getData('routine_home_menus') ?: [];//TODO 首页按钮
        $roll = GroupDataService::getData('routine_home_roll_news') ?: [];//TODO 首页滚动新闻
        $activity = GroupDataService::getData('routine_home_activity', 3) ?: [];//TODO 首页活动区域图片
        $site_name = sysConfig('site_name');
        $routine_index_page = GroupDataService::getData('routine_index_page');
        $info['fastInfo'] = $routine_index_page[0]['fast_info'] ?? '';//sysConfig('fast_info');//TODO 快速选择简介
        $info['bastInfo'] = $routine_index_page[0]['bast_info'] ?? '';//sysConfig('bast_info');//TODO 精品推荐简介
        $info['firstInfo'] = $routine_index_page[0]['first_info'] ?? '';//sysConfig('first_info');//TODO 首发新品简介
        $info['salesInfo'] = $routine_index_page[0]['sales_info'] ?? '';//sysConfig('sales_info');//TODO 促销单品简介
        $logoUrl = sysConfig('routine_index_logo');//TODO 促销单品简介
        if (strstr($logoUrl, 'http') === false) $logoUrl = sysConfig('site_url') . $logoUrl;
        $logoUrl = str_replace('\\', '/', $logoUrl);
        $fastNumber = $routine_index_page[0]['fast_number'] ?? 6;//sysConfig('fast_number');//TODO 快速选择分类个数
        $bastNumber = $routine_index_page[0]['bast_number'] ?? 6;//sysConfig('bast_number');//TODO 精品推荐个数
        $firstNumber = $routine_index_page[0]['first_number'] ?? 6;//sysConfig('first_number');//TODO 首发新品个数
        $info['fastList'] = StoreCategory::byIndexList((int)$fastNumber);//TODO 快速选择分类个数
        $info['bastList'] = StoreProduct::getBestProduct('id,image,store_name,cate_id,price,ot_price,IFNULL(sales,0) + IFNULL(ficti,0) as sales,unit_name', (int)$bastNumber, $request->uid());//TODO 精品推荐个数
        $info['firstList'] = StoreProduct::getNewProduct('id,image,store_name,cate_id,price,unit_name,IFNULL(sales,0) + IFNULL(ficti,0) as sales', (int)$firstNumber, $request->uid());//TODO 首发新品个数
        $info['bastBanner'] = GroupDataService::getData('routine_home_bast_banner') ?? [];//TODO 首页精品推荐图片
        $benefit = StoreProduct::getBenefitProduct('id,image,store_name,cate_id,price,ot_price,stock,unit_name', 3);//TODO 首页促销单品
        $lovely = GroupDataService::getData('routine_home_new_banner') ?: [];//TODO 首发新品顶部图
        $likeInfo = StoreProduct::getHotProduct('id,image,store_name,cate_id,price,unit_name', 3);//TODO 热门榜单 猜你喜欢
        $couponList = StoreCouponIssue::getIssueCouponList($request->uid(), 3);
        $subscribe = WechatUser::where('uid', $request->uid() ?? 0)->value('subscribe') ? true : false;
        return app('json')->successful(compact('banner', 'menus', 'roll', 'info', 'activity', 'lovely', 'benefit', 'likeInfo', 'logoUrl', 'couponList', 'site_name','subscribe'));
    }

    /**
     * 获取分享配置
     * @return mixed
     */
    public function share()
    {
        $data['img'] = sysConfig('wechat_share_img');
        if (strstr($data['img'], 'http') === false) $data['img'] = sysConfig('site_url') . $data['img'];
        $data['img'] = str_replace('\\', '/', $data['img']);
        $data['title'] = sysConfig('wechat_share_title');
        $data['synopsis'] = sysConfig('wechat_share_synopsis');
        return app('json')->successful(compact('data'));
    }


    /**
     * 获取个人中心菜单
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function menu_user(Request $request)
    {
        $menusInfo = GroupDataService::getData('routine_my_menus') ?? [];
        $user = $request->user();
        $vipOpen = sysConfig('vip_open');
        $vipOpen = is_string($vipOpen) ? (int)$vipOpen : $vipOpen;
        foreach ($menusInfo as $key => &$value) {
            $value['pic'] = UtilService::setSiteUrl($value['pic']);
            if ($value['id'] == 137 && !(intval(sysConfig('store_brokerage_statu')) == 2 || $user->is_promoter == 1))
                unset($menusInfo[$key]);
            if ($value['id'] == 174 && !StoreService::orderServiceStatus($user->uid))
                unset($menusInfo[$key]);
            if (!StoreService::orderServiceStatus($user->uid) && $value['wap_url'] === '/order/order_cancellation')
                unset($menusInfo[$key]);
            if ($value['wap_url'] == '/user/vip' && !$vipOpen)
                unset($menusInfo[$key]);
            if ($value['wap_url'] == '/customer/index' && !StoreService::orderServiceStatus($user->uid))
                unset($menusInfo[$key]);
        }
        return app('json')->successful(['routine_my_menus' => $menusInfo]);
    }

    /**
     * 热门搜索关键字获取
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function search()
    {
        $routineHotSearch = GroupDataService::getData('routine_hot_search') ?? [];
        $searchKeyword = [];
        if (count($routineHotSearch)) {
            foreach ($routineHotSearch as $key => &$item) {
                array_push($searchKeyword, $item['title']);
            }
        }
        return app('json')->successful($searchKeyword);
    }


    /**
     * 图片上传
     * @param Request $request
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function upload_image(Request $request)
    {
        $data = UtilService::postMore([
            ['filename', 'file'],
        ], $request);
        if (!$data['filename']) return app('json')->fail('参数有误');
        if (Cache::has('start_uploads_' . $request->uid()) && Cache::get('start_uploads_' . $request->uid()) >= 100) return app('json')->fail('非法操作');
        $res = UploadService::instance()->setUploadPath('store/comment')->image($data['filename']);
        if (!is_array($res)) return app('json')->fail($res);
        SystemAttachment::attachmentAdd($res['name'], $res['size'], $res['type'], $res['dir'], $res['thumb_path'], 1, $res['image_type'], $res['time'], 2);
        if (Cache::has('start_uploads_' . $request->uid()))
            $start_uploads = (int)Cache::get('start_uploads_' . $request->uid());
        else
            $start_uploads = 0;
        $start_uploads++;
        Cache::set('start_uploads_' . $request->uid(), $start_uploads, 86400);
        $res['dir'] = UploadService::pathToUrl($res['dir']);
        if (strpos($res['dir'], 'http') === false) $res['dir'] = $request->domain() . $res['dir'];
        return app('json')->successful('图片上传成功!', ['name' => $res['name'], 'url' => $res['dir']]);
    }

    /**
     * 物流公司
     * @return mixed
     */
    public function logistics()
    {
        $expressList = Express::lst();
        if (!$expressList) return app('json')->successful([]);
        return app('json')->successful($expressList->hidden(['code', 'id', 'sort', 'is_show'])->toArray());
    }

    /**
     * 短信购买异步通知
     *
     * @param Request $request
     * @return mixed
     */
    public function sms_pay_notify(Request $request)
    {
        list($order_id, $price, $status, $num, $pay_time, $attach) = UtilService::postMore([
            ['order_id', ''],
            ['price', 0.00],
            ['status', 400],
            ['num', 0],
            ['pay_time', time()],
            ['attach', 0],
        ], $request, true);
        if ($status == 200) {
            ChannelService::instance()->send('PAY_SMS_SUCCESS', ['price' => $price, 'number' => $num], [$attach]);
            return app('json')->successful();
        }
        return app('json')->fail();
    }

    /**
     * 记录用户分享
     * @param Request $request
     * @return mixed
     */
    public function user_share(Request $request)
    {
        return app('json')->successful(UserBill::setUserShare($request->uid()));
    }

    /**
     * 获取图片base64
     * @param Request $request
     * @return mixed
     */
    public function get_image_base64(Request $request)
    {
        list($imageUrl, $codeUrl) = UtilService::postMore([
            ['image', ''],
            ['code', ''],
        ], $request, true);
        try {
            $code = $codeUrl ? UtilService::setImageBase64($codeUrl) : false;
            $image = $imageUrl ? UtilService::setImageBase64($imageUrl) : false;
            return app('json')->successful(compact('code', 'image'));
        } catch (\Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }


}