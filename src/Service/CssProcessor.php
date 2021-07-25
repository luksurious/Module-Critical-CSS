<?php

namespace M2Boilerplate\CriticalCss\Service;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Url\CssResolver;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;

class CssProcessor
{
    /** @var CssResolver */
    protected $cssResolver;

    /** @var StoreManager */
    protected $storeManager;

    public function __construct(
        CssResolver $cssResolver,
        StoreManager $storeManager
    )
    {
        $this->cssResolver = $cssResolver;
        $this->storeManager = $storeManager;
    }

    public function process(string $cssContent, bool $noDomain = false)
    {
        $pattern = '@(\.\./)*(/static|/pub/static)/(.+)$@i'; // matches paths that contain pub/static/ or just static/

        if ($noDomain) {
            $baseUrl = '/';
        } else {
            /** @var Store $store */
            $store = $this->storeManager->getStore();
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        }

        return $this->cssResolver->replaceRelativeUrls($cssContent, function ($path) use ($pattern, $baseUrl) {
            $matches = [];
            if(preg_match($pattern, $path, $matches[0])) {
                /**
                 * ../../../../../../pub/static/version/frontend/XXX/YYY/de_DE/ZZZ/asset.ext
                 * becomes
                 * https://base.url/pub/static/version/frontend/XXX/YYY/de_DE/ZZZ/asset.ext
                 */
                return $baseUrl . ltrim($matches[0][0], '/');
            }
            return $path;
        });
    }

}
