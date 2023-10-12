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
        $Articles = $Order->getArticles();

        if (!$Articles->count()) {
            return '';
        }

        $Articles->setCurrency($Order->getCurrency());
        $UniqueArticles = $Articles->toUniqueList();
        $UniqueArticles->hideHeader();

        $Engine->assign([
            'UniqueArticles' => $UniqueArticles
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Basket.html');
    }
}
