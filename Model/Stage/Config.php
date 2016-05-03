<?php

namespace Gene\BlueFoot\Model\Stage;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Gene\BlueFoot\Api\ContentBlockGroupRepositoryInterface;

/**
 * Class Plugin
 *
 * @package Gene\BlueFoot\Model\Stage
 *
 * @author Dave Macaulay <dave@gene.co.uk>
 */
class Config extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @var \Gene\BlueFoot\Model\Stage\Structural
     */
    protected $_structural;

    /**
     * @var \Gene\BlueFoot\Model\ResourceModel\Attribute\ContentBlock\CollectionFactory
     */
    protected $_contentBlockCollection;

    /**
     * @var \Gene\BlueFoot\Api\ContentBlockGroupRepositoryInterface
     */
    protected $_contentBlockGroupRepository;

    /**
     * @var \Magento\Eav\Model\EntityFactory
     */
    protected $_eavEntityFactory;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory
     */
    protected $_groupCollection;

    /**
     * @var \Gene\BlueFoot\Model\ResourceModel\Attribute\CollectionFactory
     */
    protected $_attributeCollection;

    /**
     * @var array
     */
    protected $_attributeData;

    /**
     * @var \Gene\BlueFoot\Model\Config\ConfigInterface
     */
    protected $_configInterface;

    /**
     * @var \Magento\Framework\View\LayoutFactory
     */
    protected $_layoutFactory;

    /**
     * @var \Gene\BlueFoot\Model\ResourceModel\Stage\Template\CollectionFactory
     */
    protected $_templateCollection;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var \Gene\BlueFoot\Model\ResourceModel\Entity
     */
    protected $_entity;

    /**
     * Config constructor.
     *
     * @param \Magento\Framework\Model\Context                                            $context
     * @param \Magento\Framework\Registry                                                 $registry
     * @param \Gene\BlueFoot\Model\Stage\Structural                                       $structural
     * @param \Gene\BlueFoot\Model\ResourceModel\Attribute\ContentBlock\CollectionFactory $contentBlockCollectionFactory
     * @param \Gene\BlueFoot\Api\ContentBlockGroupRepositoryInterface                     $contentBlockGroupRepository
     * @param \Magento\Eav\Model\EntityFactory                                            $eavEntityFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory   $groupCollectionFactory
     * @param \Gene\BlueFoot\Model\ResourceModel\Attribute\CollectionFactory              $attributeCollection
     * @param \Gene\BlueFoot\Model\Config\ConfigInterface                                 $configInterface
     * @param \Magento\Framework\View\LayoutFactory                                       $layoutFactory
     * @param \Gene\BlueFoot\Model\ResourceModel\Stage\Template\CollectionFactory         $templateCollectionFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder                                $searchCriteriaBuilder
     * @param \Gene\BlueFoot\Model\ResourceModel\Entity                                   $entity
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null                $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null                          $resourceCollection
     * @param array                                                                       $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Gene\BlueFoot\Model\Stage\Structural $structural,
        \Gene\BlueFoot\Model\ResourceModel\Attribute\ContentBlock\CollectionFactory $contentBlockCollectionFactory,
        ContentBlockGroupRepositoryInterface $contentBlockGroupRepository,
        \Magento\Eav\Model\EntityFactory $eavEntityFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory $groupCollectionFactory,
        \Gene\BlueFoot\Model\ResourceModel\Attribute\CollectionFactory $attributeCollection,
        \Gene\BlueFoot\Model\Config\ConfigInterface $configInterface,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Gene\BlueFoot\Model\ResourceModel\Stage\Template\CollectionFactory $templateCollectionFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        \Gene\BlueFoot\Model\ResourceModel\Entity $entity,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_structural = $structural;
        $this->_contentBlockCollection = $contentBlockCollectionFactory;
        $this->_contentBlockGroupRepository = $contentBlockGroupRepository;
        $this->_eavEntityFactory = $eavEntityFactory;
        $this->_groupCollection = $groupCollectionFactory;
        $this->_attributeCollection = $attributeCollection;
        $this->_configInterface = $configInterface;
        $this->_layoutFactory = $layoutFactory;
        $this->_templateCollection = $templateCollectionFactory;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_entity = $entity;

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Return the config for the page builder instance
     *
     * @return array
     */
    public function getConfig()
    {
        $config = [
            'contentTypeGroups' => $this->getContentBlockGroups(),
            'contentTypes' => $this->getContentBlockData(),
            'structural' => $this->_structural->getStructuralConfig(),
            'templates' => $this->getTemplateData(),
            'globalFields' => $this->getGlobalFields()
        ];

        return $config;
    }

    /**
     * Retrieve the content block groups
     *
     * @return array
     */
    public function getContentBlockGroups()
    {
        $groups = [];

        /* @var $groupsSearchResults \Magento\Framework\Api\SearchResults */
        $groupsSearchResults = $this->_contentBlockGroupRepository->getList($this->_searchCriteriaBuilder->create());
        foreach ($groupsSearchResults->getItems() as $group) {
            $groups[$group['id']] = [
                'icon' => $group['icon'],
                'name' => $group['name'],
                'sort' => $group['sort_order']
            ];
        }

        return $groups;
    }

    /**
     * Build up template data
     *
     * @return array
     */
    public function getTemplateData()
    {
        $templates = $this->_templateCollection->create();

        if ($templates->getSize()) {
            $templateData = array();
            foreach ($templates as $template) {
                $templateData[] = array(
                    'id' => $template->getId(),
                    'name' => $template->getData('name'),
                    'structure' => $template->getData('structure')
                );
            }
            return $templateData;
        }

        return [];
    }

    /**
     * Return any global fields
     *
     * @return mixed
     */
    public function getGlobalFields()
    {
        return $this->_configInterface->getGlobalFields();
    }

    /**
     * Build up the content block data
     *
     * @return array
     */
    public function getContentBlockData()
    {
        // Retrieve content blocks
        $contentBlocks = $this->_contentBlockCollection->create();
        $contentBlocks->setOrder('entity_type.sort_order', \Magento\Framework\Data\Collection::SORT_ORDER_ASC);
        $contentBlocks->setEntityTypeFilter($this->_eavEntityFactory->create()->setType(\Gene\BlueFoot\Model\Entity::ENTITY)->getTypeId());

        // Don't load in the default attribute set
        $contentBlocks->addFieldToFilter('main_table.attribute_set_id', array('neq' => $this->_entity->getEntityType()->getDefaultAttributeSetId()));

        $contentBlockData = [];
        /* @var $contentBlock \Gene\BlueFoot\Model\Attribute\ContentBlock */
        foreach ($contentBlocks as $contentBlock) {
            $contentBlockData[$contentBlock->getIdentifier()] = $this->_flattenContentBlockData($contentBlock);
        }

        return $contentBlockData;
    }

    /**
     * Flatten the content block data
     *
     * @param \Gene\BlueFoot\Model\Attribute\ContentBlock $contentBlock
     *
     * @return array
     */
    protected function _flattenContentBlockData(\Gene\BlueFoot\Model\Attribute\ContentBlock $contentBlock)
    {
        $this->_buildAllAttributeData();

        $fields = $this->_getContentBlockFields($contentBlock, $this->_getAttributeGroupData($contentBlock));

        $contentBlockData = [
            'code' => $contentBlock->getIdentifier(),
            'name' => $contentBlock->getName(),
            'icon' => '<i class="' . $contentBlock->getIconClass() . '"></i>',
            'color' => '#444',
            'color_theme' => $this->_getColorTheme('#444'),
            'contentType' => '',
            'group' => ($contentBlock->getGroupId() ? $contentBlock->getGroupId() : 'general'),
            'fields' => $fields,
            'fields_list' => array_keys($fields),
            'visible' => (bool) $contentBlock->getShowInPageBuilder()
        ];

        // Do we have a preview template for this content block?
        if ($previewTemplate = $this->_getPreviewTemplate($contentBlock)) {
            $contentBlockData['preview_template'] = $previewTemplate;
        }

        return $contentBlockData;
    }

    /**
     * Get the preview template for the content block
     *
     * @param \Gene\BlueFoot\Model\Attribute\ContentBlock $contentBlock
     *
     * @return bool
     */
    protected function _getPreviewTemplate(\Gene\BlueFoot\Model\Attribute\ContentBlock $contentBlock)
    {
        if ($template = $contentBlock->getItemViewTemplate()) {
            $templatePath = $this->_configInterface->getTemplate($template);
            if ($templatePath && isset($templatePath['file'])) {
                try {
                    /* @var $block \Magento\Framework\View\Element\Template */
                    $block = $this->_layoutFactory->create()->createBlock('Magento\Backend\Block\Template');
                    $block->setTemplate('Gene_BlueFoot::' . $templatePath['file']);
                    if ($block) {
                        return $block->toHtml();
                    }
                } catch (\Exception $e) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Build all attribute data at once for efficiency
     */
    protected function _buildAllAttributeData()
    {
        $attributes = $this->_attributeCollection->create();
        if ($attributes->getSize()) {
            /* @var $attribute \Gene\BlueFoot\Model\Attribute */
            foreach ($attributes as $attribute) {
                $this->_attributeData[$attribute->getAttributeCode()] = $this->_flattenAttributeData($attribute);
            }
        }
    }

    /**
     * Return the attribute group data
     *
     * @param \Gene\BlueFoot\Model\Attribute\ContentBlock $contentBlock
     *
     * @return array
     */
    protected function _getAttributeGroupData(\Gene\BlueFoot\Model\Attribute\ContentBlock $contentBlock)
    {
        $groups = $this->_groupCollection->create();
        $groups->setAttributeSetFilter($contentBlock->getId());

        $groupData = [];
        /* @var $group \Magento\Eav\Model\Entity\Attribute\Group */
        foreach ($groups as $group) {
            $attributeCollection = $this->_attributeCollection->create();
            $attributeCollection
                ->setAttributeGroupFilter($group->getId())
                ->setAttributeSetFilter($contentBlock->getId());

            foreach ($attributeCollection->getAllIds() as $attributeId) {
                $groupData[$attributeId] = $group->getAttributeGroupName();
            }
        }

        return $groupData;
    }

    /**
     * Return all fields assigned to a content block
     *
     * @param \Gene\BlueFoot\Model\Attribute\ContentBlock $contentBlock
     * @param                                             $groupData
     *
     * @return array
     */
    protected function _getContentBlockFields(\Gene\BlueFoot\Model\Attribute\ContentBlock $contentBlock, $groupData)
    {
        $attributes = $contentBlock->getAllAttributes();
        if ($attributes) {
            $fields = [];
            /* @var $attribute \Gene\BlueFoot\Model\Attribute */
            foreach ($attributes as $attribute) {
                if ($attributeData = $this->_getAttributeData($attribute)) {
                    // Assign the data from the getAttributeData call
                    $fields[$attribute->getAttributeCode()] = $attributeData;
                    // Assign the group from the group data
                    $fields[$attribute->getAttributeCode()]['group'] = isset($groupData[$attribute->getId()]) ?  $groupData[$attribute->getId()] : 'General';
                }
            }

            return $fields;
        }

        return [];
    }

    /**
     * Retrieve attribute data from the classes built information
     *
     * @param \Gene\BlueFoot\Model\Attribute $attribute
     *
     * @return array
     */
    protected function _getAttributeData(\Gene\BlueFoot\Model\Attribute $attribute)
    {
        if (isset($this->_attributeData[$attribute->getAttributeCode()])) {
            return $this->_attributeData[$attribute->getAttributeCode()];
        }

        return [];
    }

    /**
     * Flatten a single attributes data ready for the stage
     *
     * @param \Gene\BlueFoot\Model\Attribute $attribute
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _flattenAttributeData(\Gene\BlueFoot\Model\Attribute $attribute)
    {
        $options = [];
        if($attribute->usesSource()){
            $options = $attribute->getSource()->getAllOptions();
        }

        // Assign the type for later manipulation
        $type = $attribute->getFrontend()->getInputType();

        $data = [
            'attribute_id' => $attribute->getId(),
            'code' => $attribute->getAttributeCode(),
            'type' => $type,
            'label' => $attribute->getFrontend()->getLabel(),
            'is_global' => $attribute->getIsGlobal(),
            'group' => 'General'
        ];

        // Only pass options if they aren't empty
        if (!empty($options)) {
            $data['options'] = $options;
        }

        if ($attribute->getNote()) {
            $data['note'] = $attribute->getNote();
        }

        // Pass over if the attribute is required
        if ($attribute->getIsRequired()) {
            $data['required'] = true;
        }

        // Inform the front-end if this field has a data model
        if ($attribute->getData('data_model')) {
            $data['data_model'] = true;
        }

        $childType = false;
        if($type == 'child_entity'){
            if($sourceModel = $attribute->getSource()){
                if(method_exists($sourceModel, 'getAllowedContentBlock')){
                    $childTypeModel = $sourceModel->getAllowedContentBlock();
                    if($childTypeModel){
                        $childType = $childTypeModel->getIdentifier();
                    }
                }
            }
        }

        // Handle different types
        switch($type) {
            case 'boolean':
                $data['type'] = 'select';
                $data['options'] = [
                    ['value' => 0, 'label' => __('No')],
                    ['value' => 1, 'label' => __('Yes')]
                ];
                break;
            case 'multiselect':
                $data['type'] = 'select';
                $data['multiple'] = true;
                break;
            case 'textarea':
                if($attribute->getIsWysiwygEnabled()) {
                    $data['type'] = 'widget';
                    $data['widget'] = 'wysiwyg';
                }
                break;
            case 'image':
            case 'file':
            case 'upload':
                $data['type'] = 'widget';
                $data['widget'] = 'upload';
                break;

            case 'child_entity':
                $data['type'] = 'widget';
                $data['widget'] = 'child_block';
                $data['child_block_type'] = $childType;
                break;

        }

        // If the attribute has a widget assigned to it ensure it renders on the front-end
        if($widget = $attribute->getData('widget')) {
            $data['type'] = 'widget';
            $data['widget'] = $widget;
        }

        return $data;
    }

    /**
     * Send a color theme based on the content types color
     *
     * @param $hex
     *
     * @return string
     */
    protected function _getColorTheme($hex)
    {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));

        $contrast = sqrt(
            $r * $r * .241 +
            $g * $g * .691 +
            $b * $b * .068
        );

        if($contrast > 190){
            return 'dark';
        } else {
            return 'light';
        }
    }
}