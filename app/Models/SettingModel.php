<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    public const TYPES = ['string', 'text', 'int', 'decimal', 'bool', 'time', 'json'];

    protected $table            = 'settings';
    protected $primaryKey       = 'key';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $allowedFields    = ['key', 'value', 'group', 'type', 'label', 'description', 'sort_order'];
    protected $useTimestamps    = true;

    protected $validationRules = [
        'key'   => 'required|max_length[100]|regex_match[/^[a-z0-9_.]+$/]',
        'group' => 'required|max_length[50]',
        'type'  => 'required|in_list[string,text,int,decimal,bool,time,json]',
        'label' => 'required|max_length[150]',
    ];
}
