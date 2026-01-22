<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace app\services\system\attachment;

use app\services\BaseServices;
use app\dao\system\attachment\SystemAttachmentDao;
use app\services\product\product\CopyTaobaoServices;
use crmeb\exceptions\AdminException;
use crmeb\exceptions\ApiException;
use crmeb\exceptions\UploadException;
use app\services\other\UploadService;
use app\services\product\product\StoreDescriptionServices;
use app\services\product\product\StoreProductServices;
use app\services\product\sku\StoreProductAttrValueServices;
use app\services\product\product\StoreCategoryServices;
use app\services\article\ArticleServices;
use app\services\article\ArticleContentServices;
use app\services\system\config\SystemGroupDataServices;
use app\services\system\config\SystemConfigServices;
use app\services\wechat\WechatQrcodeServices;
use app\services\diy\DiyServices;
use app\services\activity\seckill\StoreSeckillServices;
use app\services\activity\bargain\StoreBargainServices;
use app\services\activity\combination\StoreCombinationServices;
use think\facade\Log;

/**
 *
 * Class SystemAttachmentServices
 * @package app\services\attachment
 * @method getYesterday() 获取昨日生成数据
 * @method delYesterday() 删除昨日生成数据
 * @method scanUploadImage($scan_token) 获取扫码上传的图片数据
 */
class SystemAttachmentServices extends BaseServices
{

    /**
     * SystemAttachmentServices constructor.
     * @param SystemAttachmentDao $dao
     */
    public function __construct(SystemAttachmentDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取单个资源
     * @param array $where
     * @param string $field
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getInfo(array $where, string $field = '*')
    {
        return $this->dao->getOne($where, $field);
    }

    /**
     * 获取图片列表
     * @param array $where
     * @return array
     */
    public function getImageList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getList($where, $page, $limit);
        $site_url = sys_config('site_url');
        foreach ($list as &$item) {
            if ($site_url) {
                $item['satt_dir'] = (strpos($item['satt_dir'], $site_url) !== false || strstr($item['satt_dir'], 'http') !== false) ? $item['satt_dir'] : $site_url . $item['satt_dir'];
                $item['att_dir'] = (strpos($item['att_dir'], $site_url) !== false || strstr($item['att_dir'], 'http') !== false) ? $item['satt_dir'] : $site_url . $item['att_dir'];
                $item['time'] = date('Y-m-d H:i:s', $item['time']);
            }
        }
        $where['module_type'] = 1;
        $count = $this->dao->count($where);
        return compact('list', 'count');
    }

    /**
     * 删除图片
     * @param string $ids
     */
    public function del(string $ids)
    {
        $ids = explode(',', $ids);
        if (empty($ids)) throw new AdminException(400599);
        
        $errorMsgs = [];
        
        foreach ($ids as $v) {
            $attinfo = $this->dao->get((int)$v);
            if ($attinfo) {
                try {
                    $this->checkIsUsed($attinfo);
                    // 如果检查通过，执行物理文件删除
                    $upload = UploadService::init($attinfo['image_type']);
                    if ($attinfo['image_type'] == 1) {
                        // 本地存储
                        $path = $attinfo['att_dir'];
                        if (strpos($path, 'http') === 0) {
                            $parsed = parse_url($path);
                            $path = $parsed['path'] ?? '';
                        }
                        if (strpos($path, '/') === 0) {
                            $path = substr($path, 1);
                        }
                        if ($path) $upload->delete($path);
                    } else {
                        // 云存储：从att_dir中提取key（去掉域名部分）
                        $key = $attinfo['att_dir'];
                        if (strpos($key, 'http') === 0) {
                            // 从完整URL中提取路径部分作为key
                            $parsed = parse_url($key);
                            $key = ltrim($parsed['path'] ?? '', '/');
                        }
                        if ($key) $upload->delete($key);
                    }
                    $this->dao->delete((int)$v);
                } catch (\Throwable $e) {
                     // 捕获 checkIsUsed 抛出的业务异常或其他异常
                     $errorMsgs[] = $e->getMessage();
                }
            }
        }
        
        if (!empty($errorMsgs)) {
            // 将所有错误信息合并抛出，使用 <br> 换行 (前端需开启 dangerouslyUseHTMLString)
            throw new AdminException("以下素材正在使用中，无法删除：<br>" . implode("<br>", $errorMsgs));
        }
    }

    /**
     * 图片上传
     * @param int $pid
     * @param string $file
     * @param int $upload_type
     * @param int $type
     * @return mixed
     */
    public function upload(int $pid, string $file, int $upload_type, int $type, $menuName, $uploadToken = '')
    {
        $realName = false;
        if ($upload_type == 0) {
            $upload_type = sys_config('upload_type', 1);
        }
        if ($menuName == 'weixin_ckeck_file' || $menuName == 'ico_path') {
            $upload_type = 1;
            $realName = true;
        }
        try {
            $path = make_path('attach', 2, true);
            if ($path === '') {
                throw new AdminException(400555);
            }
            $upload = UploadService::init($upload_type);
            $res = $upload->to($path)->validate()->move($file, $realName);
            if ($res === false) {
                throw new UploadException($upload->getError());
            } else {
                $fileInfo = $upload->getUploadInfo();
                $fileType = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
                if ($fileInfo && $type == 0 && !in_array($fileType, ['xlsx', 'xls', 'mp4'])) {
                    $data['name'] = $fileInfo['name'];
                    $data['real_name'] = $fileInfo['real_name'];
                    $data['att_dir'] = $fileInfo['dir'];
                    $data['satt_dir'] = $fileInfo['thumb_path'];
                    $data['att_size'] = $fileInfo['size'];
                    $data['att_type'] = $fileInfo['type'];
                    $data['image_type'] = $upload_type;
                    $data['module_type'] = 1;
                    $data['time'] = $fileInfo['time'] ?? time();
                    $data['pid'] = $pid;
                    $data['scan_token'] = $uploadToken;
                    $this->dao->save($data);
                }
                return $res->filePath;
            }
        } catch (\Exception $e) {
            throw new UploadException($e->getMessage());
        }
    }

    /**
     * @param array $data
     * @return \crmeb\basic\BaseModel
     */
    public function move(array $data)
    {
        $this->dao->move($data);
    }

    /**
     * 添加信息
     * @param array $data
     */
    public function save(array $data)
    {
        $this->dao->save($data);
    }

    /**
     * TODO 添加附件记录
     * @param $name
     * @param $att_size
     * @param $att_type
     * @param $att_dir
     * @param string $satt_dir
     * @param int $pid
     * @param int $imageType
     * @param int $time
     * @return SystemAttachment
     */
    public function attachmentAdd($name, $att_size, $att_type, $att_dir, $satt_dir = '', $pid = 0, $imageType = 1, $time = 0, $module_type = 1, $type = 0, $real_name = '')
    {
        $data['name'] = $name;
        $data['att_dir'] = $att_dir;
        $data['satt_dir'] = $satt_dir;
        $data['att_size'] = $att_size;
        $data['att_type'] = $att_type;
        $data['image_type'] = $imageType;
        $data['module_type'] = $module_type;
        $data['time'] = $time ?: time();
        $data['pid'] = $pid;
        $data['type'] = $type;
        $data['real_name'] = $real_name != '' ? $real_name : $name;
        if (!$this->dao->save($data)) {
            throw new ApiException(100022);
        }
        return true;
    }

    /**
     * 推广名片生成
     * @param $name
     */
    public function getLikeNameList($name)
    {
        return $this->dao->getLikeNameList(['like_name' => $name], 0, 0);
    }

    /**
     * 清除昨日海报
     * @return bool
     * @throws \Exception
     */
    public function emptyYesterdayAttachment(): bool
    {
        try {
            $list = $this->dao->getYesterday();
            foreach ($list as $key => $item) {
                $upload = UploadService::init((int)$item['image_type']);
                if ($item['image_type'] == 1) {
                    $att_dir = $item['att_dir'];
                    if ($att_dir && strstr($att_dir, 'uploads') !== false) {
                        if (strstr($att_dir, 'http') === false)
                            $upload->delete($att_dir);
                        else {
                            $filedir = substr($att_dir, strpos($att_dir, 'uploads'));
                            if ($filedir) $upload->delete($filedir);
                        }
                    }
                } else {
                    if ($item['name']) $upload->delete($item['name']);
                }
            }
            $this->dao->delYesterday();
            return true;
        } catch (\Exception $e) {
            $this->dao->delYesterday();
            return true;
        }
    }

    /**
     * 视频分片上传
     * @param $data
     * @param $file
     * @return mixed
     */
    public function videoUpload($data, $file)
    {
        $pathinfo = pathinfo($data['filename']);
        if (isset($pathinfo['extension']) && !in_array($pathinfo['extension'], ['avi', 'mp4', 'wmv', 'rm', 'mpg', 'mpeg', 'mov', 'flv', 'swf'])) {
            throw new AdminException(400558);
        }
        $data['chunkNumber'] = (int)$data['chunkNumber'];
        $public_dir = app()->getRootPath() . 'public';
        $dir = '/uploads/attach/' . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR . date('d');
        $all_dir = $public_dir . $dir;
        if (!is_dir($all_dir)) mkdir($all_dir, 0777, true);
        $filename = $all_dir . '/' . $data['filename'] . '__' . $data['chunkNumber'];
        move_uploaded_file($file['tmp_name'], $filename);
        $res['code'] = 0;
        $res['msg'] = 'error';
        $res['file_path'] = '';
        if ($data['chunkNumber'] == $data['totalChunks']) {
            $blob = '';
            for ($i = 1; $i <= $data['totalChunks']; $i++) {
                $blob .= file_get_contents($all_dir . '/' . $data['filename'] . '__' . $i);
            }
            file_put_contents($all_dir . '/' . $data['filename'], $blob);
            for ($i = 1; $i <= $data['totalChunks']; $i++) {
                @unlink($all_dir . '/' . $data['filename'] . '__' . $i);
            }
            if (file_exists($all_dir . '/' . $data['filename'])) {
                $res['code'] = 2;
                $res['msg'] = 'success';
                $res['file_path'] = sys_config('site_url') . $dir . '/' . $data['filename'];
            }
        } else {
            if (file_exists($all_dir . '/' . $data['filename'] . '__' . $data['chunkNumber'])) {
                $res['code'] = 1;
                $res['msg'] = 'waiting';
                $res['file_path'] = '';
            }
        }
        return $res;
    }

    /**
     * 网络图片上传
     * @param $data
     * @return bool
     * @throws \Exception
     * @author 吴汐
     * @email 442384644@qq.com
     * @date 2023/06/13
     */
    public function onlineUpload($data)
    {
        //生成附件目录
        if (make_path('attach', 3, true) === '') {
            throw new AdminException(400555);
        }

        //上传图片
        /** @var SystemAttachmentServices $systemAttachmentService */
        $systemAttachmentService = app()->make(SystemAttachmentServices::class);
        $siteUrl = sys_config('site_url');

        foreach ($data['images'] as $image) {
            $uploadValue = app()->make(CopyTaobaoServices::class)->downloadImage($image);
            if (is_array($uploadValue)) {
                //TODO 拼接图片地址
                if ($uploadValue['image_type'] == 1) {
                    $imagePath = $siteUrl . $uploadValue['path'];
                } else {
                    $imagePath = $uploadValue['path'];
                }
                //写入数据库
                if (!$uploadValue['is_exists']) {
                    $systemAttachmentService->save([
                        'name' => $uploadValue['name'],
                        'real_name' => $uploadValue['name'],
                        'att_dir' => $imagePath,
                        'satt_dir' => $imagePath,
                        'att_size' => $uploadValue['size'],
                        'att_type' => $uploadValue['mime'],
                        'image_type' => $uploadValue['image_type'],
                        'module_type' => 1,
                        'time' => time(),
                        'pid' => $data['pid']
                    ]);
                }
            }
        }
        return true;
    }

    /**
     * 检查重复文件（全局检查）
     * @param array $filenames 文件名数组
     * @return array 返回重复的文件信息
     */
    public function checkDuplicateFiles(array $filenames): array
    {
        $duplicates = [];
        $siteUrl = sys_config('site_url');
        
        foreach ($filenames as $filename) {
            $existing = $this->dao->getOne([
                'real_name' => $filename,
                'module_type' => 1
            ]);
            
            if ($existing) {
                $attDir = $existing['att_dir'];
                if ($siteUrl && strpos($attDir, 'http') === false) {
                    $attDir = $siteUrl . $attDir;
                }
                $duplicates[] = [
                    'name' => $filename,
                    'att_id' => $existing['att_id'],
                    'att_dir' => $attDir,
                    'satt_dir' => $existing['satt_dir'] ? ($siteUrl && strpos($existing['satt_dir'], 'http') === false ? $siteUrl . $existing['satt_dir'] : $existing['satt_dir']) : $attDir
                ];
            }
        }
        
        return $duplicates;
    }

    /**
     * 删除指定附件（用于替换时先删除旧文件）
     * @param array $attIds 附件ID数组
     * @return bool
     */
    public function deleteByIds(array $attIds): bool
    {
        if (empty($attIds)) {
            return true;
        }
        $this->del(implode(',', $attIds));
        return true;
    }

    /**
     * 检查素材是否被使用
     * @param $attinfo
     */
    private function checkIsUsed($attinfo)
    {
        $path = $attinfo['att_dir'];
        // 统一把反斜杠转为正斜杠，避免Windows环境下的路径差异
        $path = str_replace('\\', '/', $path);
        
        
        // 获取文件名 (MD5+后缀，足够唯一)
        $filename = basename($path);

        /** @var StoreProductServices $productServices */
        $productServices = app()->make(StoreProductServices::class);
        /** @var StoreProductAttrValueServices $skuServices */
        $skuServices = app()->make(StoreProductAttrValueServices::class);
        /** @var StoreDescriptionServices $descriptionServices */
        $descriptionServices = app()->make(StoreDescriptionServices::class);
        /** @var StoreCategoryServices $categoryServices */
        $categoryServices = app()->make(StoreCategoryServices::class);
        /** @var ArticleServices $articleServices */
        $articleServices = app()->make(ArticleServices::class);
        /** @var ArticleContentServices $articleContentServices */
        $articleContentServices = app()->make(ArticleContentServices::class);
        /** @var SystemGroupDataServices $groupDataServices */
        $groupDataServices = app()->make(SystemGroupDataServices::class);
        /** @var SystemConfigServices $configServices */
        $configServices = app()->make(SystemConfigServices::class);
        /** @var WechatQrcodeServices $qrcodeServices */
        $qrcodeServices = app()->make(WechatQrcodeServices::class);
        /** @var DiyServices $diyServices */
        $diyServices = app()->make(DiyServices::class);
        /** @var StoreSeckillServices $seckillServices */
        $seckillServices = app()->make(StoreSeckillServices::class);
        /** @var StoreBargainServices $bargainServices */
        $bargainServices = app()->make(StoreBargainServices::class);
        /** @var StoreCombinationServices $combinationServices */
        $combinationServices = app()->make(StoreCombinationServices::class);

        // 1. 检查主图/视频 (以文件名结尾)
        $product = $productServices->getOne([
            ['image|video_link', 'like', "%$filename"], 
            ['is_del', '=', 0]
        ], 'store_name');
        if ($product) throw new AdminException("【{$attinfo['real_name']}】被商品【" . $product['store_name'] . "】使用(主图/视频)");

        // 2. 检查轮播图 (JSON字符串包含文件名)
        $productSlider = $productServices->getOne([
            ['slider_image', 'like', "%$filename%"],
            ['is_del', '=', 0]
        ], 'store_name');
        if ($productSlider) throw new AdminException("【{$attinfo['real_name']}】被商品【" . $productSlider['store_name'] . "】使用(轮播图)");

        // 3. 检查规格图 (以文件名结尾)
        $sku = $skuServices->getOne([
            ['image', 'like', "%$filename"]
        ], 'product_id');
        if ($sku) {
            $productSku = $productServices->get($sku['product_id']);
            if ($productSku && $productSku['is_del'] == 0) {
                throw new AdminException("【{$attinfo['real_name']}】被商品【" . $productSku['store_name'] . "】使用(规格图)");
            }
        }
        
        // 4. 检查商品详情 (富文本)
        $description = $descriptionServices->dao->getOne([
            ['description', 'like', "%$filename%"]
        ], 'product_id');
        
        if ($description) {
            $productDesc = $productServices->get($description['product_id']);
             if ($productDesc && $productDesc['is_del'] == 0) {
                throw new AdminException("【{$attinfo['real_name']}】被商品【" . $productDesc['store_name'] . "】使用(商品详情)");
            }
        }

        // 5. 检查商品分类 (图标/大图)
        $category = $categoryServices->getOne([
            ['pic|big_pic', 'like', "%$filename"],
            ['is_show', '=', 1]
        ], 'cate_name');
        if ($category) throw new AdminException("【{$attinfo['real_name']}】被分类【" . $category['cate_name'] . "】使用");

        // 6. 检查文章列表 (封面图)
        $article = $articleServices->getOne([
            ['image_input', 'like', "%$filename%"]
        ], 'title');
        if ($article) throw new AdminException("【{$attinfo['real_name']}】被文章【" . $article['title'] . "】使用(封面)");

        // 7. 检查文章详情 (富文本)
        $articleContent = $articleContentServices->dao->getOne([
            ['content', 'like', "%$filename%"]
        ], 'nid');
        if ($articleContent) {
            $art = $articleServices->get($articleContent['nid']);
            if ($art) {
                throw new AdminException("【{$attinfo['real_name']}】被文章【" . $art['title'] . "】使用(文章详情)");
            }
        }
        
        // 8. 检查系统配置 (Logo、H5配置等)
        $sysConfig = $configServices->getOne([
            ['value', 'like', "%$filename%"]
        ]);
        if ($sysConfig) throw new AdminException("【{$attinfo['real_name']}】被系统配置【" . $sysConfig['menu_name'] . "】使用");

        // 9. 检查渠道二维码
        $qrcode = $qrcodeServices->getOne([
            ['image', 'like', "%$filename%"]
        ]);
        if ($qrcode) throw new AdminException("【{$attinfo['real_name']}】被渠道码【" . $qrcode['name'] . "】使用");

        // 10. 检查DIY装修 (页面装修数据)
        // 移除 is_del 检查，即使是删除的页面（回收站）也保护图片
        $diy = $diyServices->getOne([
            ['value', 'like', "%$filename%"]
        ], 'name');
        if ($diy) throw new AdminException("【{$attinfo['real_name']}】被DIY装修【" . $diy['name'] . "】使用");

        // 11. 检查秒杀活动
        $seckill = $seckillServices->getOne([
            ['image|images', 'like', "%$filename%"]
        ], 'title');
        if ($seckill) throw new AdminException("【{$attinfo['real_name']}】被秒杀活动【" . $seckill['title'] . "】使用");

        // 12. 检查砍价活动
        $bargain = $bargainServices->getOne([
            ['image|images', 'like', "%$filename%"]
        ], 'title');
        if ($bargain) throw new AdminException("【{$attinfo['real_name']}】被砍价活动【" . $bargain['title'] . "】使用");

        // 13. 检查拼团活动
        $combination = $combinationServices->getOne([
             ['image|images', 'like', "%$filename%"]
        ], 'title');
        if ($combination) throw new AdminException("【{$attinfo['real_name']}】被拼团活动【" . $combination['title'] . "】使用");
        
    }
}

