<?php

namespace ovidiuro\myfinance2\App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Http\Requests\StoreTrade;
use ovidiuro\myfinance2\App\Services\FinanceUtils;

class TradeQuantityIsAvailable implements Rule
{
    /**
     * @var integer
     */
    private $_id;

    /**
     * @var string
     */
    private $_timestamp;

    /**
     * @var string
     */
    private $_action;

    /**
     * @var string
     */
    private $_account;

    /**
     * @var string
     */
    private $_accountCurrency;

    /**
     * @var string
     */
    private $_symbol;

    /**
     * @param integer $id
     * @param string  $timestamp
     * @param string  $action
     * @param string  $account
     * @param string  $accountCurrency
     * @param string  $symbol
     */
    public function __construct($id, $timestamp, $action, $account, $accountCurrency, $symbol)
    {
        $this->_id              = $id;
        $this->_timestamp       = $timestamp;
        $this->_action          = $action;
        $this->_account         = $account;
        $this->_accountCurrency = $accountCurrency;
        $this->_symbol          = $symbol;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string $attribute
     * @param  mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($this->_action != 'SELL') {
            return true;
        }

        $financeUtils = new FinanceUtils();
        $availableQuantity = $financeUtils->getAvailableQuantity($this->_symbol,
            $this->_account, $this->_accountCurrency, $this->_timestamp, $this->_id);

        if ($value > $availableQuantity) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute is invalid as it is bigger than the available quantity.';
    }
}

