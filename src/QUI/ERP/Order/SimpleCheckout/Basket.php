<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;

use function dirname;

class Basket extends QUI\Control
{
    protected Checkout $Checkout;

    /**
     * @param Checkout $Checkout
     * @param mixed[] $attributes
     */
    public function __construct(Checkout $Checkout, array $attributes = [])
    {
        $this->Checkout = $Checkout;

        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Basket.css');
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Order = $this->Checkout->getOrder();
        $Order?->recalculate(); // because of price factors
        $Articles = $Order?->getArticles();

        if (!$Articles || !$Articles->count()) {
            $Engine->assign([
                'basketEmpty' => true
            ]);

            return $Engine->fetch(dirname(__FILE__) . '/Basket.html');
        }

        $Articles->setCurrency($Order->getCurrency());
        $UniqueArticles = $Articles->toUniqueList();
        $UniqueArticles->hideHeader();

        $Engine->assign('disableProductLinks', $this->Checkout->getAttribute('disableProductLinks'));

        $basketHtml = $UniqueArticles->toHTML(
            dirname(__FILE__) . '/Basket.ArticleList.html',
            false,
            $Engine
        );

        if ($this->getAttribute('basketForHeader')) {
            $basketHtml = $UniqueArticles->toHTML(
                dirname(__FILE__) . '/Basket.ForHeader.html',
                false,
                $Engine
            );
        }

        $Engine->assign([
            'basketEmpty' => false,
            'UniqueArticles' => $UniqueArticles,
            'basketHtml' => $basketHtml
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Basket.html');
    }
}
