<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Secure order access shared by the order tools and the guided flows.
 *
 * Access rule: a logged-in customer may see their own orders; anyone else must give the order's email address
 * and have verified it in this conversation.
 */
class OrderLookup
{
    public const ERROR_NOT_FOUND = 'not_found';
    public const ERROR_VERIFICATION_REQUIRED = 'verification_required';
    public const ERROR_EMAIL_REQUIRED = 'email_required';

    private const INVOICE_LINK_TTL = 1800;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var ShippingHelper
     */
    private $shippingHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var Cards
     */
    private $cards;

    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        ShippingHelper $shippingHelper,
        StoreManagerInterface $storeManager,
        DeploymentConfig $deploymentConfig,
        DateTime $dateTime,
        Cards $cards
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->shippingHelper = $shippingHelper;
        $this->storeManager = $storeManager;
        $this->deploymentConfig = $deploymentConfig;
        $this->dateTime = $dateTime;
        $this->cards = $cards;
    }

    /**
     * @return array{order?: Order, error?: string, message?: string}
     */
    public function find(ChatContext $context, string $incrementId, ?string $email): array
    {
        $incrementId = preg_replace('/\D+/', '', $incrementId) ?? '';
        if ($incrementId === '') {
            return ['error' => self::ERROR_NOT_FOUND, 'message' => 'Please give the order number (it is on your confirmation email, e.g. 000000123).'];
        }
        $collection = $this->orderCollectionFactory->create()->addFieldToFilter('increment_id', $incrementId)->setPageSize(1);
        /** @var Order $order */
        $order = $collection->getFirstItem();
        if (!$order->getId()) {
            // Customers often drop leading zeros.
            $collection = $this->orderCollectionFactory->create()
                ->addFieldToFilter('increment_id', ['like' => '%' . ltrim($incrementId, '0')])
                ->setPageSize(1);
            $order = $collection->getFirstItem();
        }
        if (!$order->getId()) {
            return ['error' => self::ERROR_NOT_FOUND, 'message' => sprintf('I could not find an order with number %s. Please check the number and try again.', $incrementId)];
        }
        if ($context->isLoggedIn() && (int)$order->getCustomerId() === (int)$context->getCustomerId()) {
            return ['order' => $order];
        }
        $email = mb_strtolower(trim((string)$email));
        if ($email === '') {
            return ['error' => self::ERROR_EMAIL_REQUIRED, 'message' => 'For your security, please also give the email address used on the order.'];
        }
        if ($email !== mb_strtolower((string)$order->getCustomerEmail())) {
            // Do not reveal whether the order exists for a different email.
            return ['error' => self::ERROR_NOT_FOUND, 'message' => sprintf('I could not find order %s for %s. Please check both and try again.', $incrementId, $email)];
        }
        if (!$context->getConversation()->isEmailVerified($email)) {
            return ['error' => self::ERROR_VERIFICATION_REQUIRED, 'message' => sprintf('To protect your order details I need to verify %s first with a one-time code.', $email), 'email' => $email];
        }
        return ['order' => $order];
    }

    public function summarize(Order $order, bool $includeItems = true): array
    {
        $currency = (string)$order->getOrderCurrencyCode();
        $summary = [
            'increment_id' => $order->getIncrementId(),
            'status' => (string)$order->getStatusLabel(),
            'state' => (string)$order->getState(),
            'created_at' => $order->getCreatedAt() ? date('j M Y', strtotime((string)$order->getCreatedAt())) : null,
            'grand_total' => $this->cards->format((float)$order->getGrandTotal(), $currency),
            'subtotal' => $this->cards->format((float)$order->getSubtotal(), $currency),
            'shipping_amount' => $this->cards->format((float)$order->getShippingAmount(), $currency),
            'discount_amount' => (float)$order->getDiscountAmount() != 0.0 ? $this->cards->format(abs((float)$order->getDiscountAmount()), $currency) : null,
            'currency' => $currency,
            'payment_method' => $this->paymentTitle($order),
            'shipping_method' => (string)$order->getShippingDescription(),
            'shipping_address' => $this->address($order),
            'items' => [],
            'shipments' => [],
            'invoices' => [],
            'refunded' => (float)$order->getTotalRefunded() > 0 ? $this->cards->format((float)$order->getTotalRefunded(), $currency) : null,
        ];
        if ($includeItems) {
            foreach ($order->getAllVisibleItems() as $item) {
                $summary['items'][] = [
                    'name' => $item->getName(),
                    'sku' => $item->getSku(),
                    'qty' => (float)$item->getQtyOrdered(),
                    'qty_shipped' => (float)$item->getQtyShipped(),
                    'qty_refunded' => (float)$item->getQtyRefunded(),
                    'price' => $this->cards->format((float)$item->getPriceInclTax() ?: (float)$item->getPrice(), $currency),
                ];
            }
        }
        foreach ($order->getShipmentsCollection() as $shipment) {
            $tracks = [];
            foreach ($shipment->getAllTracks() as $track) {
                $tracks[] = [
                    'carrier' => (string)$track->getTitle(),
                    'carrier_code' => (string)$track->getCarrierCode(),
                    'number' => (string)$track->getTrackNumber(),
                ];
            }
            $summary['shipments'][] = [
                'increment_id' => $shipment->getIncrementId(),
                'created_at' => $shipment->getCreatedAt() ? date('j M Y', strtotime((string)$shipment->getCreatedAt())) : null,
                'tracks' => $tracks,
            ];
        }
        if ($summary['shipments'] !== []) {
            try {
                $summary['tracking_url'] = $this->shippingHelper->getTrackingPopupUrlBySalesModel($order);
            } catch (\Throwable $e) {
                $summary['tracking_url'] = null;
            }
        }
        foreach ($order->getInvoiceCollection() as $invoice) {
            $summary['invoices'][] = [
                'increment_id' => $invoice->getIncrementId(),
                'created_at' => $invoice->getCreatedAt() ? date('j M Y', strtotime((string)$invoice->getCreatedAt())) : null,
                'grand_total' => $this->cards->format((float)$invoice->getGrandTotal(), $currency),
                'download_url' => $this->invoiceLink((int)$order->getId(), (int)$invoice->getId(), (int)$order->getStoreId()),
            ];
        }
        return $summary;
    }

    /**
     * Signed, expiring link to Magento's own invoice PDF, safe for guests (no account login needed).
     */
    public function invoiceLink(int $orderId, int $invoiceId, int $storeId): string
    {
        $expires = $this->dateTime->gmtTimestamp() + self::INVOICE_LINK_TTL;
        $signature = $this->sign($orderId, $invoiceId, $expires);
        return $this->storeManager->getStore($storeId)->getUrl('aichatbot/order/invoice', [
            'order' => $orderId,
            'invoice' => $invoiceId,
            'exp' => $expires,
            'sig' => $signature,
        ]);
    }

    public function isValidInvoiceLink(int $orderId, int $invoiceId, int $expires, string $signature): bool
    {
        if ($expires < $this->dateTime->gmtTimestamp()) {
            return false;
        }
        return hash_equals($this->sign($orderId, $invoiceId, $expires), $signature);
    }

    private function sign(int $orderId, int $invoiceId, int $expires): string
    {
        $key = (string)$this->deploymentConfig->get('crypt/key');
        return hash_hmac('sha256', $orderId . '|' . $invoiceId . '|' . $expires, $key);
    }

    private function paymentTitle(Order $order): ?string
    {
        try {
            $payment = $order->getPayment();
            if (!$payment) {
                return null;
            }
            $title = (string)$payment->getMethodInstance()->getTitle();
            return $title !== '' ? $title : (string)$payment->getMethod();
        } catch (\Throwable $e) {
            return $order->getPayment() ? (string)$order->getPayment()->getMethod() : null;
        }
    }

    private function address(Order $order): ?string
    {
        $address = $order->getShippingAddress() ?: $order->getBillingAddress();
        if (!$address) {
            return null;
        }
        return implode(', ', array_filter([
            trim((string)$address->getFirstname() . ' ' . (string)$address->getLastname()),
            $address->getCity(),
            $address->getRegion(),
            $address->getCountryId(),
        ]));
    }
}
