<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the ChatContext for storefront requests: a long-lived, httpOnly session cookie identifies the visitor
 * (works for guests and stays stable across logins), customer and cart come from the Magento sessions.
 */
class ContextFactory
{
    public const COOKIE = 'wb_aichatbot_sid';
    private const COOKIE_DAYS = 30;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        RemoteAddress $remoteAddress,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->remoteAddress = $remoteAddress;
        $this->scopeConfig = $scopeConfig;
    }

    public function createFromRequest(RequestInterface $request): ChatContext
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $context = new ChatContext($this->sessionHash(), $storeId);
        $context->setLanguage(substr((string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId), 0, 2) ?: 'en');
        $ip = (string)$this->remoteAddress->getRemoteAddress();
        $context->setIpHash($ip !== '' ? hash('sha256', $ip) : null);
        $pageUrl = (string)$request->getParam('page_url', '');
        $context->setPageUrl($pageUrl !== '' ? mb_substr($pageUrl, 0, 512) : null);

        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $context->setCustomer(
                (int)$customer->getId(),
                (string)$customer->getEmail(),
                trim((string)$customer->getFirstname() . ' ' . (string)$customer->getLastname()),
                (int)$customer->getGroupId()
            );
        }
        try {
            $quote = $this->checkoutSession->getQuote();
            $context->setQuote($quote && $quote->getId() ? $quote : null);
        } catch (\Throwable $e) {
            $context->setQuote(null);
        }
        return $context;
    }

    /**
     * Reads or creates the visitor cookie and returns its hash (only the hash is ever stored).
     */
    public function sessionHash(): string
    {
        $sid = (string)$this->cookieManager->getCookie(self::COOKIE, '');
        if (!preg_match('/^[a-f0-9]{32}$/', $sid)) {
            $sid = bin2hex(random_bytes(16));
            $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setDuration(self::COOKIE_DAYS * 86400)
                ->setPath('/')
                ->setHttpOnly(true)
                ->setSecure($this->storeManager->getStore()->isCurrentlySecure())
                ->setSameSite('Lax');
            $this->cookieManager->setPublicCookie(self::COOKIE, $sid, $metadata);
        }
        return hash('sha256', $sid);
    }

    public function forgetSession(): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()->setPath('/');
        $this->cookieManager->deleteCookie(self::COOKIE, $metadata);
    }
}
