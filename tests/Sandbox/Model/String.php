<?php
namespace Sandbox\Model;

class String extends \Sandbox\Model\MOA\String {
    public function afterInsert () {
        if ($this->data['name'] === 'throw_after_insert') {
            throw new \RuntimeException('', 1);
        }
    }

    public function afterUpdate () {
        if ($this->data['name'] === 'throw_after_update') {
            throw new \RuntimeException('', 2);
        }
    }

    public function afterDelete () {
        if ($this->data['name'] === 'throw_after_delete') {
            throw new \RuntimeException('', 3);
        }
    }
}