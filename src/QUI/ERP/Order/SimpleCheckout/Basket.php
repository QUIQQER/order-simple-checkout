<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;
use QUI\ERP\Order\Basket\Exception;
use QUI\ERP\Order\Basket\ExceptionBasketNotFound;

use function dirname;

class Basket extends QUI\Control
{
    protected Checkout $Checkout;

    /**
     * @throws ExceptionBasketNotFound
     * @throws Exception
     * @throws QUI\Database\Exception
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
