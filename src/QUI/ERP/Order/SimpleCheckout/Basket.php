<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;

use function dirname;

class Basket extends QUI\Control
{
    protected Checkout $Checkout;

    /**
     */
    public function __construct(Checkout $Checkout, $attributes = [])
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
        $Order->recalculate(); // because of price factors
        $Articles = $Order->getArticles();

        if (!$Articles->count()) {
            $Engine->assign([
                'basketEmpty' => true
            ]);

            return $Engine->fetch(dirname(__FILE__) . '/Basket.html');
        }

        $Articles->setCurrency($Order->getCurrency());
        $UniqueArticles = $Articles->toUniqueList();
        $UniqueArticles->hideHeader();

        $basketHtml = $UniqueArticles->toHTML(dirname(__FILE__) . '/Basket.ArticleList.html');

        if ($this->getAttribute('basketForHeader')) {
            $basketHtml = $UniqueArticles->toHTML(dirname(__FILE__) . '/Basket.ForHeader.html');
        }

        $Engine->assign([
            'basketEmpty' => false,
            'UniqueArticles' => $UniqueArticles,
            'basketHtml'     => $basketHtml
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Basket.html');
    }
}
