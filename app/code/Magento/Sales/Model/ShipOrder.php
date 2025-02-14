<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Sales\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\OrderStateResolverInterface;
use Magento\Sales\Model\Order\ShipmentDocumentFactory;
use Magento\Sales\Model\Order\Shipment\NotifierInterface;
use Magento\Sales\Model\Order\Shipment\OrderRegistrarInterface;
use Magento\Sales\Model\Order\Validation\ShipOrderInterface as ShipOrderValidator;
use Psr\Log\LoggerInterface;

/**
 * Class ShipOrder
 *
 * Save shipment and order data
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ShipOrder implements ShipOrderInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ShipmentDocumentFactory
     */
    private $shipmentDocumentFactory;

    /**
     * @var OrderStateResolverInterface
     */
    private $orderStateResolver;

    /**
     * @var OrderConfig
     */
    private $config;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * @var ShipOrderValidator
     */
    private $shipOrderValidator;

    /**
     * @var NotifierInterface
     */
    private $notifierInterface;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRegistrarInterface
     */
    private $orderRegistrar;

    /**
     * @var OrderMutexInterface
     */
    private $orderMutex;

    /**
     * @param ResourceConnection $resourceConnection
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentDocumentFactory $shipmentDocumentFactory
     * @param OrderStateResolverInterface $orderStateResolver
     * @param OrderConfig $config
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param ShipOrderValidator $shipOrderValidator
     * @param NotifierInterface $notifierInterface
     * @param OrderRegistrarInterface $orderRegistrar
     * @param LoggerInterface $logger
     * @param OrderMutex|null $orderMutex
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        OrderRepositoryInterface $orderRepository,
        ShipmentDocumentFactory $shipmentDocumentFactory,
        OrderStateResolverInterface $orderStateResolver,
        OrderConfig $config,
        ShipmentRepositoryInterface $shipmentRepository,
        ShipOrderValidator $shipOrderValidator,
        NotifierInterface $notifierInterface,
        OrderRegistrarInterface $orderRegistrar,
        LoggerInterface $logger,
        ?OrderMutexInterface $orderMutex = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->orderRepository = $orderRepository;
        $this->shipmentDocumentFactory = $shipmentDocumentFactory;
        $this->orderStateResolver = $orderStateResolver;
        $this->config = $config;
        $this->shipmentRepository = $shipmentRepository;
        $this->shipOrderValidator = $shipOrderValidator;
        $this->notifierInterface = $notifierInterface;
        $this->logger = $logger;
        $this->orderRegistrar = $orderRegistrar;
        $this->orderMutex = $orderMutex ?: ObjectManager::getInstance()->get(OrderMutexInterface::class);
    }

    /**
     * Process the shipment and save shipment and order data
     *
     * @param int $orderId
     * @param \Magento\Sales\Api\Data\ShipmentItemCreationInterface[] $items
     * @param bool $notify
     * @param bool $appendComment
     * @param \Magento\Sales\Api\Data\ShipmentCommentCreationInterface|null $comment
     * @param \Magento\Sales\Api\Data\ShipmentTrackCreationInterface[] $tracks
     * @param \Magento\Sales\Api\Data\ShipmentPackageCreationInterface[] $packages
     * @param \Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface|null $arguments
     * @return int
     * @throws \Magento\Sales\Api\Exception\DocumentValidationExceptionInterface
     * @throws \Magento\Sales\Api\Exception\CouldNotShipExceptionInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \DomainException
     */
    public function execute(
        $orderId,
        array $items = [],
        $notify = false,
        $appendComment = false,
        ?\Magento\Sales\Api\Data\ShipmentCommentCreationInterface $comment = null,
        array $tracks = [],
        array $packages = [],
        ?\Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface $arguments = null
    ) {
        return $this->orderMutex->execute(
            (int)$orderId,
            \Closure::fromCallable([$this, 'createShipment']),
            [
                $orderId,
                $items,
                $notify,
                $appendComment,
                $comment,
                $tracks,
                $packages,
                $arguments
            ]
        );
    }

    /**
     * Creates shipment for provided order ID
     *
     * @param int $orderId
     * @param \Magento\Sales\Api\Data\ShipmentItemCreationInterface[] $items
     * @param bool $notify
     * @param bool $appendComment
     * @param \Magento\Sales\Api\Data\ShipmentCommentCreationInterface|null $comment
     * @param \Magento\Sales\Api\Data\ShipmentTrackCreationInterface[] $tracks
     * @param \Magento\Sales\Api\Data\ShipmentPackageCreationInterface[] $packages
     * @param \Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface|null $arguments
     * @return int
     * @throws \Magento\Sales\Api\Exception\DocumentValidationExceptionInterface
     * @throws \Magento\Sales\Api\Exception\CouldNotShipExceptionInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \DomainException
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function createShipment(
        $orderId,
        array $items = [],
        $notify = false,
        $appendComment = false,
        ?\Magento\Sales\Api\Data\ShipmentCommentCreationInterface $comment = null,
        array $tracks = [],
        array $packages = [],
        ?\Magento\Sales\Api\Data\ShipmentCreationArgumentsInterface $arguments = null
    ) {
        $order = $this->orderRepository->get($orderId);
        $shipment = $this->shipmentDocumentFactory->create(
            $order,
            $items,
            $tracks,
            $comment,
            ($appendComment && $notify),
            $packages,
            $arguments
        );
        $validationMessages = $this->shipOrderValidator->validate(
            $order,
            $shipment,
            $items,
            $notify,
            $appendComment,
            $comment,
            $tracks,
            $packages
        );
        if ($validationMessages->hasMessages()) {
            throw new \Magento\Sales\Exception\DocumentValidationException(
                __("Shipment Document Validation Error(s):\n" . implode("\n", $validationMessages->getMessages()))
            );
        }
        try {
            $this->orderRegistrar->register($order, $shipment);
            $shipment = $this->shipmentRepository->save($shipment);
            if ($order->getState() === Order::STATE_NEW) {
                $order->setState(
                    $this->orderStateResolver->getStateForOrder($order, [OrderStateResolverInterface::IN_PROGRESS])
                );
                $order->setStatus($this->config->getStateDefaultStatus($order->getState()));
            }
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new \Magento\Sales\Exception\CouldNotShipException(
                __('Could not save a shipment, see error log for details')
            );
        }

        if ($notify) {
            if (!$appendComment) {
                $comment = null;
            }
            $this->notifierInterface->notify($order, $shipment, $comment);
        }
        return $shipment->getEntityId();
    }
}
