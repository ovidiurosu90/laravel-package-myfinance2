<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Enums;

enum FundingRole: string
{
    case SOURCE       = 'source';
    case INTERMEDIARY = 'intermediary';
    case INVESTMENT   = 'investment';
    case OTHER        = 'other';
}

