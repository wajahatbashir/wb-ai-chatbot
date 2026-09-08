<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Sync\Provider;

use Magento\Directory\Model\Config\Source\Country as CountrySource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use WB\AiChatbot\Model\Kb\Sync\SyncDocument;
use WB\AiChatbot\Model\Kb\Sync\SyncProviderInterface;

/**
 * One "About the store" document per store view: name, contact details, address, hours, currency, shipping origin.
 */
class StoreInfo implements SyncProviderInterface
{
    public const CODE = 'store_info';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CountrySource
     */
    private $countrySource;

    public function __construct(ScopeConfigInterface $scopeConfig, CountrySource $countrySource)
    {
        $this->scopeConfig = $scopeConfig;
        $this->countrySource = $countrySource;
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getTitle(): string
    {
        return 'Store Information';
    }

    public function getCategoryName(): string
    {
        return 'Store Information';
    }

    public function fetch(StoreInterface $store): \Generator
    {
        $document = $this->build($store);
        if ($document) {
            yield $document;
        }
    }

    public function fetchOne(StoreInterface $store, string $identifier): ?SyncDocument
    {
        return $identifier === self::CODE . '.' . $store->getId() ? $this->build($store) : null;
    }

    private function build(StoreInterface $store): ?SyncDocument
    {
        $storeId = (int)$store->getId();
        $get = function (string $path) use ($storeId): string {
            return trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
        };

        $name = $get('general/store_information/name') ?: (string)$store->getName();
        $currency = (string)$store->getCurrentCurrencyCode();
        $countries = $this->countryNames();
        $lines = [];
        $lines[] = 'Store name: ' . $name;
        $lines[] = 'Website: ' . rtrim((string)$store->getBaseUrl(), '/');
        $lines[] = 'Prices are shown in ' . $currency . '.';

        $phone = $get('general/store_information/phone');
        if ($phone !== '') {
            $lines[] = 'Phone: ' . $phone;
        }
        $hours = $get('general/store_information/hours');
        if ($hours !== '') {
            $lines[] = 'Opening hours: ' . $hours;
        }
        $email = $get('trans_email/ident_general/email') ?: $get('contact/email/recipient_email');
        if ($email !== '') {
            $lines[] = 'Email: ' . $email;
        }
        $address = array_filter([
            $get('general/store_information/street_line1'),
            $get('general/store_information/street_line2'),
            $get('general/store_information/city'),
            $get('general/store_information/postcode'),
            $countries[$get('general/store_information/country_id')] ?? $get('general/store_information/country_id'),
        ]);
        if ($address !== []) {
            $lines[] = 'Address: ' . implode(', ', $address);
        }
        $origin = $countries[$get('shipping/origin/country_id')] ?? $get('shipping/origin/country_id');
        if ($origin !== '') {
            $originCity = $get('shipping/origin/city');
            $lines[] = 'Orders ship from: ' . ($originCity !== '' ? $originCity . ', ' : '') . $origin;
        }
        $allowed = array_filter(explode(',', $get('general/country/allow')));
        if ($allowed !== [] && count($allowed) <= 40) {
            $lines[] = 'Countries served: ' . implode(', ', array_map(function ($code) use ($countries) {
                return $countries[$code] ?? $code;
            }, $allowed));
        } elseif ($allowed !== []) {
            $lines[] = 'Ships to ' . count($allowed) . ' countries worldwide.';
        }
        $carriers = $this->activeCarriers($storeId);
        if ($carriers !== []) {
            $lines[] = 'Shipping methods: ' . implode(', ', $carriers);
        }
        $payments = $this->activePayments($storeId);
        if ($payments !== []) {
            $lines[] = 'Payment methods: ' . implode(', ', $payments);
        }
        $lines[] = 'Contact page: ' . rtrim((string)$store->getBaseUrl(), '/') . '/contact/';

        return new SyncDocument(
            self::CODE . '.' . $storeId,
            'About ' . $name,
            implode("\n", $lines),
            rtrim((string)$store->getBaseUrl(), '/') . '/contact/',
            ['store_name' => $name, 'currency' => $currency, 'phone' => $phone, 'email' => $email]
        );
    }

    /**
     * @return string[]
     */
    private function activeCarriers(int $storeId): array
    {
        $carriers = $this->scopeConfig->getValue('carriers', ScopeInterface::SCOPE_STORE, $storeId) ?: [];
        $titles = [];
        foreach ($carriers as $carrier) {
            if (!empty($carrier['active']) && !empty($carrier['title'])) {
                $titles[] = (string)$carrier['title'];
            }
        }
        return array_values(array_unique($titles));
    }

    /**
     * @return string[]
     */
    private function activePayments(int $storeId): array
    {
        $methods = $this->scopeConfig->getValue('payment', ScopeInterface::SCOPE_STORE, $storeId) ?: [];
        $titles = [];
        foreach ($methods as $code => $method) {
            if (!empty($method['active']) && !empty($method['title']) && $code !== 'free') {
                $titles[] = (string)$method['title'];
            }
        }
        return array_values(array_unique($titles));
    }

    /**
     * @return array<string, string>
     */
    private function countryNames(): array
    {
        $names = [];
        foreach ($this->countrySource->toOptionArray() as $option) {
            if (!empty($option['value'])) {
                $names[(string)$option['value']] = (string)$option['label'];
            }
        }
        return $names;
    }
}
