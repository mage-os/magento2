<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Swatches\Helper;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Config\CatalogMediaConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;

/**
 * Helper to move images from tmp to catalog directory
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class Media extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Swatch area inside media folder
     *
     */
    public const  SWATCH_MEDIA_PATH = 'attribute/swatch';

    /**
     * @var \Magento\Catalog\Model\Product\Media\Config
     */
    protected $mediaConfig;

    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    protected $mediaDirectory;

    /**
     * Core file storage database
     *
     * @var \Magento\MediaStorage\Helper\File\Storage\Database
     */
    protected $fileStorageDb = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Image\Factory
     */
    protected $imageFactory;

    /**
     * @var \Magento\Theme\Model\ResourceModel\Theme\Collection
     */
    protected $themeCollection;

    /**
     * @var \Magento\Framework\View\ConfigInterface
     */
    protected $viewConfig;

    /**
     * @var array
     */
    protected $swatchImageTypes = ['swatch_image', 'swatch_thumb'];

    /**
     * @var array
     */
    private $imageConfig;

    /**
     * @var string
     */
    private $mediaUrlFormat;

    /**
     * @param \Magento\Catalog\Model\Product\Media\Config $mediaConfig
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\MediaStorage\Helper\File\Storage\Database $fileStorageDb
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Image\Factory $imageFactory
     * @param \Magento\Theme\Model\ResourceModel\Theme\Collection $themeCollection
     * @param \Magento\Framework\View\ConfigInterface $configInterface
     * @param CatalogMediaConfig $catalogMediaConfig
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function __construct(
        \Magento\Catalog\Model\Product\Media\Config $mediaConfig,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\MediaStorage\Helper\File\Storage\Database $fileStorageDb,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Image\Factory $imageFactory,
        \Magento\Theme\Model\ResourceModel\Theme\Collection $themeCollection,
        \Magento\Framework\View\ConfigInterface $configInterface,
        ?CatalogMediaConfig $catalogMediaConfig = null
    ) {
        $this->mediaConfig = $mediaConfig;
        $this->fileStorageDb = $fileStorageDb;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->storeManager = $storeManager;
        $this->imageFactory = $imageFactory;
        $this->themeCollection = $themeCollection;
        $this->viewConfig = $configInterface;

        $catalogMediaConfig = $catalogMediaConfig ?: ObjectManager::getInstance()->get(CatalogMediaConfig::class);
        $this->mediaUrlFormat = $catalogMediaConfig->getMediaUrlFormat();
    }

    /**
     * Method to get swatch attribute image.
     *
     * @param string $swatchType
     * @param string $file
     * @return string
     */
    public function getSwatchAttributeImage($swatchType, $file)
    {
        $basePath = $this->getSwatchMediaUrl();

        if ($this->mediaUrlFormat === CatalogMediaConfig::HASH) {
            $generationPath = $swatchType . '/' . $this->getFolderNameSize($swatchType) . $file;
            $absoluteImagePath = $this->mediaDirectory
                ->getAbsolutePath($this->getSwatchMediaPath() . '/' . $generationPath);
            if (!$this->mediaDirectory->isExist(($absoluteImagePath))) {
                try {
                    $this->generateSwatchVariations($file);
                } catch (\Exception $e) {
                    return '';
                }
            }

            return $basePath . '/' . $generationPath;
        }

        return $basePath . '/' . $this->getRelativeTransformationParametersPath($swatchType, $file);
    }

    /**
     * Method to get relative transformation parameters path.
     *
     * @param string $swatchType
     * @param string $file
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getRelativeTransformationParametersPath($swatchType, $file)
    {
        $imageConfig = $this->getImageConfig();
        return  $this->prepareFile($file) . '?' . http_build_query([
            'width' => $imageConfig[$swatchType]['width'],
            'height' => $imageConfig[$swatchType]['height'],
            'store' => $this->storeManager->getStore()->getCode(),
            'image-type' => $swatchType
        ]);
    }

    /**
     * Move image from tmp to catalog dir
     *
     * @param string $file
     * @return string path
     */
    public function moveImageFromTmp($file)
    {
        if ($file && strrpos($file, '.tmp') == strlen($file) - 4) {
            $file = substr($file, 0, strlen($file) - 4);
        }
        $destinationFile = $this->getUniqueFileName($file);

        /** @var $storageHelper \Magento\MediaStorage\Helper\File\Storage\Database */
        $storageHelper = $this->fileStorageDb;

        if ($storageHelper->checkDbUsage()) {
            $storageHelper->renameFile(
                $this->mediaConfig->getTmpMediaShortUrl($file),
                $this->mediaConfig->getMediaShortUrl($destinationFile)
            );

            $this->mediaDirectory->delete($this->mediaConfig->getTmpMediaPath($file));
            $this->mediaDirectory->delete($this->getAttributeSwatchPath($destinationFile));
        } else {
            $this->mediaDirectory->renameFile(
                $this->mediaConfig->getTmpMediaPath($file),
                $this->mediaDirectory->getDriver()->getRealPathSafety(
                    $this->getAttributeSwatchPath($destinationFile)
                )
            );
        }

        return $destinationFile !== null ? str_replace('\\', '/', $destinationFile) : '';
    }

    /**
     * Check whether file to move exists. Getting unique name
     *
     * @param string $file
     * @return string
     */
    protected function getUniqueFileName($file)
    {
        if ($this->fileStorageDb->checkDbUsage()) {
            $destFile = $this->fileStorageDb->getUniqueFilename(
                $this->mediaConfig->getBaseMediaUrlAddition(),
                $file
            );
        } else {
            //phpcs:ignore Magento2.Functions.DiscouragedFunction
            $destFile = dirname($file) . '/' . \Magento\MediaStorage\Model\File\Uploader::getNewFileName(
                $this->getOriginalFilePath($file)
            );
        }

        return $destFile;
    }

    /**
     * Method to get original file path.
     *
     * @param string $file
     * @return string
     */
    private function getOriginalFilePath($file)
    {
        return $this->mediaDirectory->getAbsolutePath($this->getAttributeSwatchPath($file));
    }

    /**
     * Generate swatch thumb and small swatch image
     *
     * @param string $imageUrl
     * @return $this
     */
    public function generateSwatchVariations($imageUrl)
    {
        if ($this->mediaUrlFormat === CatalogMediaConfig::HASH) {
            $absoluteImagePath = $this->getOriginalFilePath($imageUrl);
            foreach ($this->swatchImageTypes as $swatchType) {
                $imageConfig = $this->getImageConfig();
                $swatchNamePath = $this->generateNamePath($imageConfig, $imageUrl, $swatchType);
                $image = $this->imageFactory->create($absoluteImagePath);
                $this->setupImageProperties($image);
                $image->resize($imageConfig[$swatchType]['width'], $imageConfig[$swatchType]['height']);
                $this->setupImageProperties($image, true);
                $image->save($swatchNamePath['path_for_save'], $swatchNamePath['name']);
            }
        }

        return $this;
    }

    /**
     * Setup base image properties for resize
     *
     * @param \Magento\Framework\Image $image
     * @param bool $isSwatch
     * @return $this
     */
    protected function setupImageProperties(\Magento\Framework\Image $image, $isSwatch = false)
    {
        $image->quality(100);
        $image->constrainOnly(true);
        $image->keepAspectRatio(true);
        if ($isSwatch) {
            $image->keepFrame(true);
            $image->keepTransparency(true);
            $image->backgroundColor([255, 255, 255]);
        }
        return $this;
    }

    /**
     * Generate swatch path and name for saving
     *
     * @param array $imageConfig
     * @param string $imageUrl
     * @param string $swatchType
     * @return array
     */
    protected function generateNamePath($imageConfig, $imageUrl, $swatchType)
    {
        $fileName = $this->prepareFileName($imageUrl);
        $absolutePath = $this->mediaDirectory->getAbsolutePath($this->getSwatchCachePath($swatchType));
        return [
            'path_for_save' => $absolutePath . $this->getFolderNameSize($swatchType, $imageConfig) . $fileName['path'],
            'name' => $fileName['name']
        ];
    }

    /**
     * Generate folder name WIDTHxHEIGHT based on config in view.xml
     *
     * @param string $swatchType
     * @param array|null $imageConfig
     * @return string
     */
    public function getFolderNameSize($swatchType, $imageConfig = null)
    {
        if ($imageConfig === null) {
            $imageConfig = $this->getImageConfig();
        }
        return $imageConfig[$swatchType]['width'] . 'x' . $imageConfig[$swatchType]['height'];
    }

    /**
     * Merged config from view.xml
     *
     * @return array
     */
    public function getImageConfig()
    {
        if (!$this->imageConfig) {
            $this->imageConfig = $this->viewConfig->getViewConfig()->getMediaEntities(
                'Magento_Catalog',
                Image::MEDIA_TYPE_CONFIG_NODE
            );
        }

        return $this->imageConfig;
    }

    /**
     * Image url /m/a/magento.png return ['name' => 'magento.png', 'path => '/m/a']
     *
     * @param string $imageUrl
     * @return array
     */
    protected function prepareFileName($imageUrl)
    {
        $fileArray = explode('/', $imageUrl ?: '');
        $fileName = array_pop($fileArray);
        $filePath = implode('/', $fileArray);
        return ['name' => $fileName, 'path' => $filePath];
    }

    /**
     * Url type http://url/media/attribute/swatch/
     *
     * @return string
     */
    public function getSwatchMediaUrl()
    {
        return $this->storeManager
            ->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . $this->getSwatchMediaPath();
    }

    /**
     * Return example: attribute/swatch/m/a/magento.jpg
     *
     * @param string $file
     * @return string
     */
    public function getAttributeSwatchPath($file)
    {
        return $this->getSwatchMediaPath() . '/' . $this->prepareFile($file);
    }

    /**
     * Media swatch path
     *
     * @return string
     */
    public function getSwatchMediaPath()
    {
        return self::SWATCH_MEDIA_PATH;
    }

    /**
     * Media path with swatch_image or swatch_thumb folder
     *
     * @param string $swatchType
     * @return string
     */
    public function getSwatchCachePath($swatchType)
    {
        return self::SWATCH_MEDIA_PATH . '/' . $swatchType . '/';
    }

    /**
     * Prepare file for saving
     *
     * @param string $file
     * @return string
     */
    protected function prepareFile($file)
    {
        return $file !== null ? ltrim(str_replace('\\', '/', $file), '/') : '';
    }
}
