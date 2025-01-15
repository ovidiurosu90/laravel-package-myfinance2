<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Currency;

class CurrencyUtils
{
    private $_dictionaryByIsoCode = [];
    private $_unknownIsoCode = 'UNK';

    public function __construct($hydrateDictionaryByIsoCode = true)
    {
        if ($hydrateDictionaryByIsoCode) {
            $this->hydrateDictionaryByIsoCode();
        }
    }

    public function hydrateDictionaryByIsoCode()
    {
        $currencies = Currency::get();
        foreach ($currencies as $currency) {
            $this->_dictionaryByIsoCode[$currency->iso_code] = $currency;
        }
    }

    public function getCurrencyByIsoCode($isoCode)
    {
        if (empty($this->_dictionaryByIsoCode)) {
            $this->hydrateDictionaryByIsoCode();
        }

        if (empty($this->_dictionaryByIsoCode[$isoCode])) {
            return $this->_dictionaryByIsoCode[$this->_unknownIsoCode];
        }

        return $this->_dictionaryByIsoCode[$isoCode];
    }
}

