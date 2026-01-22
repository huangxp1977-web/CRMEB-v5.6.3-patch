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

namespace app\services\system\config;


use app\dao\system\config\SystemStorageDao;
use app\services\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\CacheService;
use crmeb\services\FormBuilder;
use app\services\other\UploadService;

/**
 * Class SystemStorageServices
 * @package app\services\system\config
 */
class SystemStorageServices extends BaseServices
{

    /**
     * SystemStorageServices constructor.
     * @param SystemStorageDao $dao
     */
    public function __construct(SystemStorageDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @param array $where
     * @return array
     */
    public function getList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $config = $this->getStorageConfig((int)$where['type']);
        $where['access_key'] = $config['accessKey'];
        $list = $this->dao->getList($where, ['*'], $page, $limit, 'add_time');
        foreach ($list as &$item) {
            if ($item['type'] != 2 || empty($item['cname'])) {
                $item['cname'] = str_replace('https://', '', $item['domain']);
            }
            $item['_add_time'] = date('Y-m-d H:i:s', $item['add_time']);
            $item['_update_time'] = date('Y-m-d H:i:s', $item['update_time']);
            $service = UploadService::init($item['type']);
            $region = $service->getRegion();
            foreach ($region as $value) {
                if (strstr($item['region'], $value['value'])) {
                    $item['_region'] = $value['label'];
                }
            }
        }
        $count = $this->dao->count($where);
        return compact('list', 'count');
    }

    /**
     * @param int $type
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function getFormStorage(int $type)
    {
        $upload = UploadService::init($type);

        $config = $this->getStorageConfig($type);
        $ruleConfig = [];
        if (!$config['accessKey']) {
            $ruleConfig = [
                FormBuilder::input('accessKey', 'AccessKeyId', $config['accessKey'] ?? '')->required(),
                FormBuilder::input('secretKey', 'AccessKeySecret', $config['secretKey'] ?? '')->required(),
            ];
        }

        if ($type === 4 && isset($config['appid']) && !$config['appid']) {
            $ruleConfig[] = FormBuilder::input('appid', 'APPID', $config['appid'] ?? '')->required();
        }

        $rule = [
            FormBuilder::input('name', '空间名称')->required(),
            FormBuilder::select('region', '空间区域')->options($upload->getRegion())->required(),
            FormBuilder::radio('acl', '读写权限', 'public-read')->options([
                ['label' => '公共读(推荐)', 'value' => 'public-read'],
                ['label' => '公共读写', 'value' => 'public-read-write'],
            ])->required(),
        ];

        $rule = array_merge($ruleConfig, $rule);
        return create_form('添加云空间', $rule, '/system/config/storage/' . $type);
    }

    /**
     * @param int $type
     * @return array
     */
    public function getStorageConfig(int $type)
    {
        $config = [
            'accessKey' => '',
            'secretKey' => ''
        ];
        switch ($type) {
            case 2://七牛
                $config = [
                    'accessKey' => sys_config('qiniu_accessKey', ''),
                    'secretKey' => sys_config('qiniu_secretKey', ''),
                ];
                break;
            case 3:// oss 阿里云
                $config = [
                    'accessKey' => sys_config('accessKey', ''),
                    'secretKey' => sys_config('secretKey', ''),
                ];
                break;
            case 4:// cos 腾讯云
                $config = [
                    'accessKey' => sys_config('tengxun_accessKey', ''),
                    'secretKey' => sys_config('tengxun_secretKey', ''),
                    'appid' => sys_config('tengxun_appid', ''),
                ];
                break;
            case 5:// cos 京东云
                $config = [
                    'accessKey' => sys_config('jd_accessKey', ''),
                    'secretKey' => sys_config('jd_secretKey', ''),
                    'storageRegion' => sys_config('jd_storageRegion', ''),

                ];
                break;
            case 6:// cos 华为云
                $config = [
                    'accessKey' => sys_config('hw_accessKey', ''),
                    'secretKey' => sys_config('hw_secretKey', ''),
                ];
                break;
            case 7:// cos 天翼云
                $config = [
                    'accessKey' => sys_config('ty_accessKey', ''),
                    'secretKey' => sys_config('ty_secretKey', ''),
                ];
                break;
        }
        return $config;
    }

    /**
     * @param int $type
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function getFormStorageConfig(int $type)
    {
        $config = $this->getStorageConfig($type);
        $rule = [
            FormBuilder::hidden('type', $type),
            FormBuilder::input('accessKey', 'AccessKeyId', $config['accessKey'] ?? '')->required(),
            FormBuilder::input('secretKey', 'AccessKeySecret', $config['secretKey'] ?? '')->required(),
        ];

        if ($type === 4) {
            $rule[] = FormBuilder::input('appid', 'APPID', $config['appid'] ?? '')->required();
        }

        if ($type === 5) {
            $rule[] = FormBuilder::input('storageRegion', 'storageRegion', $config['storageRegion'] ?? '')->required();
        }


        return create_form('配置信息', $rule, '/system/config/storage/config');
    }

    /**
     * 删除空间
     * @param int $id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function deleteStorage(int $id)
    {
        $storageInfo = $this->dao->get(['is_delete' => 0, 'id' => $id]);
        if (!$storageInfo) {
            throw new AdminException(400608);
        }
        if ($storageInfo->status) {
            throw new AdminException(400609);
        }

        try {
            $upload = UploadService::init($storageInfo->type);
            $res = $upload->deleteBucket($storageInfo->name, $storageInfo->region);
//            if (false === $res) {
//                throw new AdminException($upload->getError());
//            }
        } catch (\Throwable $e) {
//            throw new AdminException($e->getMessage());
        }
        $storageInfo->is_delete = 1;
        $storageInfo->save();

        CacheService::clear();

        return true;
    }

    public function saveConfig(int $type, array $data)
    {
        //保存配置信息
        if (1 !== $type) {
            $accessKey = $secretKey = $appid = $storageRegion = '';
            if (isset($data['accessKey']) && isset($data['secretKey']) && $data['accessKey'] && $data['secretKey']) {
                $accessKey = $data['accessKey'];
                $secretKey = $data['secretKey'];
                unset($data['accessKey'], $data['secretKey']);
            }
            if (isset($data['appid']) && $data['appid']) {
                $appid = $data['appid'];
                unset($data['appid']);
            }
            if (isset($data['storageRegion']) && $data['storageRegion']) {
                $storageRegion = $data['storageRegion'];
                unset($data['storageRegion']);
            }
            if (!$accessKey || !$secretKey) {
                return true;
            }
            /** @var SystemConfigServices $make */
            $make = app()->make(SystemConfigServices::class);
            switch ($type) {
                case 2://七牛
                    $make->update('qiniu_accessKey', ['value' => json_encode($accessKey)], 'menu_name');
                    $make->update('qiniu_secretKey', ['value' => json_encode($secretKey)], 'menu_name');
                    break;
                case 3:// oss 阿里云
                    $make->update('accessKey', ['value' => json_encode($accessKey)], 'menu_name');
                    $make->update('secretKey', ['value' => json_encode($secretKey)], 'menu_name');
                    break;
                case 4:// cos 腾讯云
                    $make->update('tengxun_accessKey', ['value' => json_encode($accessKey)], 'menu_name');
                    $make->update('tengxun_secretKey', ['value' => json_encode($secretKey)], 'menu_name');
                    $make->update('tengxun_appid', ['value' => json_encode($appid)], 'menu_name');
                    break;
                case 5:// oss 京东云
                    $make->update('jd_accessKey', ['value' => json_encode($accessKey)], 'menu_name');
                    $make->update('jd_secretKey', ['value' => json_encode($secretKey)], 'menu_name');
                    $make->update('jd_storageRegion', ['value' => json_encode($storageRegion)], 'menu_name');
                    break;
                case 6:// oss 华为云
                    $make->update('hw_accessKey', ['value' => json_encode($accessKey)], 'menu_name');
                    $make->update('hw_secretKey', ['value' => json_encode($secretKey)], 'menu_name');
                    break;
                case 7:// oss 天翼云
                    $make->update('ty_accessKey', ['value' => json_encode($accessKey)], 'menu_name');
                    $make->update('ty_secretKey', ['value' => json_encode($secretKey)], 'menu_name');
                    break;
            }
            CacheService::clear();
        }
    }

    /**
     * 保存云存储
     * @param int $type
     * @param array $data
     * @return mixed
     */
    public function saveStorage(int $type, array $data)
    {
        //保存配置信息
        $this->saveConfig($type, $data);
        if ($this->dao->count(['name' => $data['name']])) {
            throw new AdminException(400610);
        }
        //保存云存储
        $data['type'] = $type;
        $upload = UploadService::init($type);
        $res = $upload->createBucket($data['name'], $data['region'], $data['acl']);
        if (false === $res) {
            throw new AdminException($upload->getError());
        }
        if (3 === $type) {
            $data['region'] = $this->getReagionHost($type, $data['region']);
        }
        $data['domain'] = $this->getDomain($type, $data['name'], $data['region'], sys_config('tengxun_appid'));
        if (2 === $type) {
            $domianList = $upload->getDomian($data['name']);
            $data['domain'] = $domianList[count($domianList) - 1];
            $resDomain = $upload->getDomianInfo($data['domain']);
            $data['cname'] = $resDomain['cname'] ?? str_replace('https://', '', $data['domain']);
        } else {
            $data['cname'] = $data['domain'];
        }
        $data['add_time'] = time();
        $data['update_time'] = time();
        $config = $this->getStorageConfig($type);
        $data['access_key'] = $config['accessKey'];

        CacheService::clear();

        return $this->dao->save($data);
    }

    /**
     * 同步云储存桶
     * @param int $type
     * @return bool
     */
    public function synchronization(int $type)
    {
        $data = [];
        switch ($type) {
            case 2://七牛
                $config = $this->getStorageConfig($type);
                $upload = UploadService::init($type);
                $list = $upload->listbuckets();
                foreach ($list as $item) {
                    if (!$this->dao->count(['name' => $item['id'], 'access_key' => $config['accessKey']])) {
                        $data[] = [
                            'type' => $type,
                            'access_key' => $config['accessKey'],
                            'name' => $item['id'],
                            'region' => $item['region'],
                            'acl' => $item['private'] == 0 ? 'public-read' : 'private',
                            'status' => 0,
                            'is_delete' => 0,
                            'add_time' => time(),
                            'update_time' => time()
                        ];
                    }
                }
                break;
            case 3:// oss 阿里云
                $upload = UploadService::init($type);
                $list = $upload->listbuckets();
                $config = $this->getStorageConfig($type);
                foreach ($list as $item) {
                    if (!$this->dao->count(['name' => $item['name'], 'access_key' => $config['accessKey']])) {
                        $region = $this->getReagionHost($type, $item['location']);
                        $data[] = [
                            'type' => $type,
                            'access_key' => $config['accessKey'],
                            'name' => $item['name'],
                            'region' => $region,
                            'acl' => 'public-read',
                            'domain' => $this->getDomain($type, $item['name'], $region),
                            'status' => 0,
                            'is_delete' => 0,
                            'add_time' => strtotime($item['createTime']),
                            'update_time' => time()
                        ];
                    }
                }
                break;
            case 4:// cos 腾讯云
                $upload = UploadService::init($type);
                $list = $upload->listbuckets();
                if (!empty($list['Name'])) {
                    $newList = $list;
                    $list = [];
                    $list[] = $newList;
                }
                $config = $this->getStorageConfig($type);
                foreach ($list as $item) {
                    if (!$this->dao->count(['name' => $item['Name'], 'access_key' => $config['accessKey']])) {
                        $data[] = [
                            'type' => $type,
                            'access_key' => $config['accessKey'],
                            'name' => $item['Name'],
                            'region' => $item['Location'],
                            'acl' => 'public-read',
                            'status' => 0,
                            'domain' => sys_config('tengxun_appid') ? $this->getDomain($type, $item['Name'], $item['Location']) : '',
                            'is_delete' => 0,
                            'add_time' => strtotime($item['CreationDate']),
                            'update_time' => time()
                        ];
                    }
                }
                break;
            case 5:// cos 京东云
                $upload = UploadService::init($type);
                $res = $upload->listbuckets(sys_config('jd_storageRegion'));
                $list = $res['Buckets'];
                $location = explode('.', $res['@metadata']['effectiveUri'])[1] ?? 'cn-north-1';
                $config = $this->getStorageConfig($type);
                foreach ($list as $item) {
                    if (!$this->dao->count(['name' => $item['Name'], 'access_key' => $config['accessKey']])) {
                        $data[] = [
                            'type' => $type,
                            'access_key' => $config['accessKey'],
                            'name' => $item['Name'],
                            'region' => $location,
                            'acl' => 'public-read',
                            'status' => 0,
                            'domain' => $this->getDomain($type, $item['Name'], $location),
                            'is_delete' => 0,
                            'add_time' => time(),
                            'update_time' => time()
                        ];
                    }
                }
                break;
            case 6:// cos 华为云
            case 7:// cos 天翼云
                $upload = UploadService::init($type);
                $list = $upload->listbuckets();
                if (!empty($list['Name'])) {
                    $newList = $list;
                    $list = [];
                    $list[] = $newList;
                }
                $config = $this->getStorageConfig($type);
                foreach ($list as $item) {
                    if (!$this->dao->count(['name' => $item['Name'], 'access_key' => $config['accessKey']])) {
                        $data[] = [
                            'type' => $type,
                            'access_key' => $config['accessKey'],
                            'name' => $item['Name'],
                            'region' => $item['Location'],
                            'acl' => 'public-read',
                            'status' => 0,
                            'domain' => $this->getDomain($type, $item['Name'], $item['Location']),
                            'is_delete' => 0,
                            'add_time' => strtotime($item['CreationDate']),
                            'update_time' => time()
                        ];
                    }
                }
                break;
        }
        if ($data) {
            $this->dao->saveAll($data);
        }

        CacheService::clear();

        return true;
    }

    /**
     * @param int $type
     * @param string $reagion
     * @return mixed|string
     */
    public function getReagionHost(int $type, string $reagion)
    {
        $upload = UploadService::init($type);
        $reagionList = $upload->getRegion();
        foreach ($reagionList as $item) {
            if (strstr($item['value'], $reagion) !== false) {
                return $item['value'];
            }
        }
        return '';
    }

    /**
     * 获取域名
     * @param int $type
     * @param string $name
     * @param string $reagion
     * @param string $appid
     * @return string
     */
    public function getDomain(int $type, string $name, string $reagion, string $appid = '')
    {
        $domainName = '';
        switch ($type) {
            case 3:// oss 阿里云
                $domainName = 'https://' . $name . '.' . $reagion;
                break;
            case 4:// cos 腾讯云
                $domainName = 'https://' . $name . ($appid ? '-' . $appid : '') . '.cos.' . $reagion . '.myqcloud.com';
                break;
            case 5:// cos 京东云
                $domainName = 'https://' . $name . '.s3.' . $reagion . '.jdcloud-oss.com';
                break;
            case 6:// cos 华为云
                $domainName = 'https://' . $name . '.obs.' . $reagion . '.myhuaweicloud.com';
                break;
            case 7:// cos 天翼云
                $domainName = 'https://' . $name . '.obs.' . $reagion . '.ctyun.cn';
                break;
        }
        return $domainName;
    }


    /**
     * 获取云存储配置
     * @param int $type
     * @return array|string[]
     */
    public function getConfig(int $type)
    {
        $res = ['name' => '', 'region' => '', 'domain' => '', 'cdn' => ''];
        try {
            $config = $this->dao->get(['type' => $type, 'status' => 1, 'is_delete' => 0]);
            if ($config) {
                return ['name' => $config->name, 'region' => $config->region, 'domain' => $config->domain, 'cdn' => $config->cdn];
            }
        } catch (\Throwable $e) {
        }
        return $res;

    }

    /**
     * 获取修改域名表单
     * @param int $id
     * @return array
     * @throws \FormBuilder\Exception\FormBuilderException
     */
    public function getUpdateDomainForm(int $id)
    {
        $storage = $this->dao->get(['id' => $id], ['domain', 'cdn']);
        $rule = [
            FormBuilder::input('domain', '空间域名', $storage['domain']),
            FormBuilder::input('cdn', 'cdn域名', $storage['cdn']),
        ];
        return create_form('修改空间域名', $rule, '/system/config/storage/domain/' . $id);
    }

    /**
     * 修改域名并绑定
     * @param int $id
     * @param string $domain
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function updateDomain(int $id, string $domain, array $data = [])
    {
        $info = $this->dao->get($id);
        if (!$info) {
            throw new AdminException(100026);
        }
        if ($info->domain != $domain) {
            $info->domain = $domain;
            $upload = UploadService::init($info->type);
            //是否添加过域名不存在需要绑定域名
            $domainList = $upload->getDomian($info->name, $info->region);
            $domainParse = parse_url($domain);
            if (false === $domainParse) {
                throw new AdminException('域名输入有误');
            }
            if (!in_array($domainParse['host'], $domainList)) {
                //绑定域名到云储存桶
                $res = $upload->bindDomian($info->name, $domain, $info->region);
                if (false === $res) {
                    throw new AdminException($upload->getError());
                }
            }
            //七牛云需要通过接口获取cname
            if (2 === ((int)$info->type)) {
                $resDomain = $upload->getDomianInfo($domain);
                $info->cname = $resDomain['cname'] ?? '';
            }
            $info->save();
        }
        if ($info->cdn != $data['cdn']) {
            $info->cdn = $data['cdn'];
            $info->save();
        }

        CacheService::clear();

        return true;
    }

    /**
     * 获取待同步的本地文件数量（包含图片和视频）
     * @return int
     */
    public function getPendingSyncCount(): int
    {
        $uploadType = (int)sys_config('upload_type', 1);
        // 仅当当前存储类型不是本地时才有同步需求
        if ($uploadType === 1) {
            return 0;
        }
        // 直接查询数据库，确保包含所有 module_type（图片和视频）
        return \app\model\system\attachment\SystemAttachment::where('image_type', 1)->count();
    }

    /**
     * 执行本地文件同步到云存储
     * @param int $limit 每批处理数量
     * @return array ['success' => int, 'failed' => int, 'remaining' => int, 'total' => int, 'errors' => array]
     */
    public function syncLocalToCloud(int $limit = 5): array
    {
        $uploadType = (int)sys_config('upload_type', 1);
        if ($uploadType === 1) {
            throw new AdminException('当前存储类型为本地，无需同步');
        }

        // 直接查询模型，不经过 DAO 以避免 module_type 过滤
        $list = \app\model\system\attachment\SystemAttachment::where('image_type', 1)
            ->limit($limit)
            ->select()
            ->toArray();
        
        // 获取总数用于进度显示
        $total = \app\model\system\attachment\SystemAttachment::where('image_type', 1)->count();
        
        if (empty($list)) {
            // 同步完成，清理空目录
            $this->cleanEmptyDirectories();
            
            // 同步完成后，自动更新商品表中的图片URL域名
            // 从站点URL获取旧域名，从云存储配置获取新域名
            $siteUrl = sys_config('site_url', '');
            $uploadType = (int)sys_config('upload_type', 1);
            $storageConfig = $this->getConfig($uploadType);  // 使用 getConfig 获取完整配置（包含 domain）
            $newDomain = $storageConfig['domain'] ?? '';
            
            if ($siteUrl && $newDomain) {
                // 提取域名部分（去掉协议）
                $oldDomainParsed = parse_url($siteUrl, PHP_URL_HOST);
                $newDomainParsed = parse_url($newDomain, PHP_URL_HOST) ?: $newDomain;
                
                if ($oldDomainParsed && $newDomainParsed && $oldDomainParsed !== $newDomainParsed) {
                    $this->updateProductTableUrls($oldDomainParsed, $newDomainParsed);
                }
            }
            
            // 清除系统缓存，确保DIY装修页面等使用最新数据
            CacheService::clear();
            
            return ['success' => 0, 'failed' => 0, 'remaining' => 0, 'total' => $total, 'errors' => []];
        }

        $upload = \app\services\other\UploadService::init($uploadType);
        /** @var \app\services\system\attachment\SystemAttachmentServices $attachmentServices */
        $attachmentServices = app()->make(\app\services\system\attachment\SystemAttachmentServices::class);
        $success = 0;
        $failed = 0;
        $errors = [];
        $dirsToCheck = [];

        foreach ($list as $item) {
            try {
                // 构建本地文件路径
                $localPath = $item['att_dir'];
                
                // 如果是完整URL，提取路径部分
                if (strpos($localPath, 'http') === 0) {
                    $parsed = parse_url($localPath);
                    $localPath = isset($parsed['path']) ? $parsed['path'] : $localPath;
                }
                
                if (strpos($localPath, '/') === 0) {
                    $localPath = ltrim($localPath, '/');
                }
                $fullPath = app()->getRootPath() . 'public/' . $localPath;
                
                // 记录目录以便稍后检查清理
                $dir = dirname($fullPath);
                if (!in_array($dir, $dirsToCheck)) {
                    $dirsToCheck[] = $dir;
                }
                
                if (!file_exists($fullPath)) {
                    // 文件不存在，记录错误并跳过，不要修改数据库
                    $errors[] = "本地文件丢失: {$item['att_dir']}";
                    $failed++;
                    continue;
                }

                // 读取文件内容
                $fileContent = file_get_contents($fullPath);
                if ($fileContent === false) {
                    $errors[] = "读取文件失败: {$item['att_dir']}";
                    $failed++;
                    continue;
                }

                // 生成云存储路径（保持原有目录结构）
                $key = $localPath;
                
                // 上传到云存储
                $result = $upload->stream($fileContent, $key);
                if ($result === false || (is_object($result) && isset($result->error))) {
                    $errors[] = "上传失败: {$item['att_dir']} - " . ($upload->getError() ?: '未知错误');
                    $failed++;
                    continue;
                }

                // 获取云存储 URL
                $cloudUrl = is_object($result) ? $result->filePath : $result['filePath'] ?? '';
                if (empty($cloudUrl)) {
                    $errors[] = "获取云存储URL失败: {$item['att_dir']}";
                    $failed++;
                    continue;
                }

                // 更新数据库记录
                $updateData = [
                    'att_dir' => $cloudUrl,
                    'satt_dir' => $cloudUrl,
                    'image_type' => $uploadType
                ];
                $attachmentServices->update($item['att_id'], $updateData);

                // 删除本地原文件
                @unlink($fullPath);
                
                // 删除本地缩略图（big_, mid_, small_ 前缀版本）
                $this->deleteThumbnails($fullPath);

                $success++;
            } catch (\Throwable $e) {
                $errors[] = "处理失败: {$item['att_dir']} - " . $e->getMessage();
                $failed++;
            }
        }

        // 获取剩余待同步数量
        $remaining = \app\model\system\attachment\SystemAttachment::where('image_type', 1)->count();

        // 清理处理过的空目录
        foreach ($dirsToCheck as $dir) {
            $this->removeEmptyDir($dir);
        }

        CacheService::clear();

        return compact('success', 'failed', 'remaining', 'total', 'errors');
    }

    /**
     * 递归删除空目录
     * @param string $dir
     */
    private function removeEmptyDir(string $dir): void
    {
        $uploadsRoot = app()->getRootPath() . 'public/uploads';
        
        // 安全检查：只删除 uploads 目录下的内容
        if (strpos($dir, $uploadsRoot) !== 0 || $dir === $uploadsRoot) {
            return;
        }
        
        // 检查目录是否存在且为空
        if (is_dir($dir) && count(scandir($dir)) === 2) { // 只有 . 和 ..
            @rmdir($dir);
            // 递归检查父目录
            $this->removeEmptyDir(dirname($dir));
        }
    }

    /**
     * 清理所有空目录
     */
    private function cleanEmptyDirectories(): void
    {
        $uploadsRoot = app()->getRootPath() . 'public/uploads';
        $this->cleanEmptyDirs($uploadsRoot);
    }

    /**
     * 递归清理空目录
     * @param string $dir
     */
    private function cleanEmptyDirs(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->cleanEmptyDirs($path);
            }
        }
        
        // 再次检查目录是否为空
        $uploadsRoot = app()->getRootPath() . 'public/uploads';
        if ($dir !== $uploadsRoot && count(scandir($dir)) === 2) {
            @rmdir($dir);
        }
    }

    /**
     * 删除文件对应的缩略图（big_, mid_, small_ 前缀版本）
     * @param string $fullPath 原文件完整路径
     */
    private function deleteThumbnails(string $fullPath): void
    {
        $dir = dirname($fullPath);
        $filename = basename($fullPath);
        
        // 缩略图前缀
        $prefixes = ['big_', 'mid_', 'small_'];
        
        foreach ($prefixes as $prefix) {
            $thumbPath = $dir . '/' . $prefix . $filename;
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
            }
        }
    }

    /**
     * 同步完成后更新所有图片引用表中的URL域名
     * 将旧的本地域名替换为云存储域名
     * @param string $oldDomain 旧域名（如 mall.aesthmed.cn）
     * @param string $newDomain 新域名（如 media.aesthmed.cn）
     * @return array ['updated' => int, 'tables' => array]
     */
    public function updateProductTableUrls(string $oldDomain, string $newDomain): array
    {
        $updated = 0;
        $tables = [];
        
        // 只替换 /uploads/ 路径，不替换 /statics/ 等系统静态资源
        $oldPattern = $oldDomain . '/uploads/';
        $newPattern = $newDomain . '/uploads/';

        // 1. 更新商品主图、视频和轮播图
        $count = \think\facade\Db::name('store_product')
            ->where('image|video_link', 'like', "%{$oldPattern}%")
            ->update([
                'image' => \think\facade\Db::raw("REPLACE(image, '{$oldPattern}', '{$newPattern}')"),
                'video_link' => \think\facade\Db::raw("REPLACE(video_link, '{$oldPattern}', '{$newPattern}')")
            ]);
        $count += \think\facade\Db::name('store_product')
            ->where('slider_image', 'like', "%{$oldPattern}%")
            ->update([
                'slider_image' => \think\facade\Db::raw("REPLACE(slider_image, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'store_product';
            $updated += $count;
        }

        // 2. 更新商品详情 (富文本HTML)
        $count = \think\facade\Db::name('store_product_description')
            ->where('description', 'like', "%{$oldPattern}%")
            ->update([
                'description' => \think\facade\Db::raw("REPLACE(description, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'store_product_description';
            $updated += $count;
        }

        // 3. 更新 SKU 规格图片
        $count = \think\facade\Db::name('store_product_attr_value')
            ->where('image', 'like', "%{$oldPattern}%")
            ->update([
                'image' => \think\facade\Db::raw("REPLACE(image, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'store_product_attr_value';
            $updated += $count;
        }

        // 4. 更新商品分类图标和大图
        $count = \think\facade\Db::name('store_category')
            ->where('pic|big_pic', 'like', "%{$oldPattern}%")
            ->update([
                'pic' => \think\facade\Db::raw("REPLACE(pic, '{$oldPattern}', '{$newPattern}')"),
                'big_pic' => \think\facade\Db::raw("REPLACE(big_pic, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'store_category';
            $updated += $count;
        }

        // 5. 更新文章封面图
        $count = \think\facade\Db::name('article')
            ->where('image_input', 'like', "%{$oldPattern}%")
            ->update([
                'image_input' => \think\facade\Db::raw("REPLACE(image_input, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'article';
            $updated += $count;
        }

        // 6. 更新文章详情 (富文本HTML)
        $count = \think\facade\Db::name('article_content')
            ->where('content', 'like', "%{$oldPattern}%")
            ->update([
                'content' => \think\facade\Db::raw("REPLACE(content, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'article_content';
            $updated += $count;
        }

        // 7. 更新系统配置 (Logo、H5配置等) - 只替换 /uploads/ 路径
        $count = \think\facade\Db::name('system_config')
            ->where('value', 'like', "%{$oldPattern}%")
            ->update([
                'value' => \think\facade\Db::raw("REPLACE(value, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'system_config';
            $updated += $count;
        }

        // 8. 更新渠道二维码
        $count = \think\facade\Db::name('wechat_qrcode')
            ->where('image', 'like', "%{$oldPattern}%")
            ->update([
                'image' => \think\facade\Db::raw("REPLACE(image, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'wechat_qrcode';
            $updated += $count;
        }

        // 9. 更新DIY装修页面 - 只替换 /uploads/ 路径
        $count = \think\facade\Db::name('diy')
            ->where('value', 'like', "%{$oldPattern}%")
            ->update([
                'value' => \think\facade\Db::raw("REPLACE(value, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'diy';
            $updated += $count;
        }

        // 10. 更新秒杀活动
        $count = \think\facade\Db::name('store_seckill')
            ->where('image|images', 'like', "%{$oldPattern}%")
            ->update([
                'image' => \think\facade\Db::raw("REPLACE(image, '{$oldPattern}', '{$newPattern}')"),
                'images' => \think\facade\Db::raw("REPLACE(images, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'store_seckill';
            $updated += $count;
        }

        // 11. 更新砍价活动
        $count = \think\facade\Db::name('store_bargain')
            ->where('image|images', 'like', "%{$oldPattern}%")
            ->update([
                'image' => \think\facade\Db::raw("REPLACE(image, '{$oldPattern}', '{$newPattern}')"),
                'images' => \think\facade\Db::raw("REPLACE(images, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'store_bargain';
            $updated += $count;
        }

        // 12. 更新拼团活动
        $count = \think\facade\Db::name('store_combination')
            ->where('image|images', 'like', "%{$oldPattern}%")
            ->update([
                'image' => \think\facade\Db::raw("REPLACE(image, '{$oldPattern}', '{$newPattern}')"),
                'images' => \think\facade\Db::raw("REPLACE(images, '{$oldPattern}', '{$newPattern}')")
            ]);
        if ($count) {
            $tables[] = 'store_combination';
            $updated += $count;
        }

        return compact('updated', 'tables');
    }
}


