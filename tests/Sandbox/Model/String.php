<?php
namespace Sandbox\Model;

class String extends \Sandbox\Model\MOA\String {
    public function afterInsert () {
        if ($this->data['name'] === 'throw_after_insert') {
            throw new \RuntimeException('', 1);
        }

        if ($this->data['name'] === 'insert_commit_transaction') {
            if (!$this->db->inTransaction()) {
                throw new \RuntimeException('There is no active transaction.');
            }

            $this->db->commit();
        }
    }

    public function afterUpdate () {
        if ($this->data['name'] === 'throw_after_update') {
            throw new \RuntimeException('', 2);
        }

        if ($this->data['name'] === 'update_commit_transaction') {
            if (!$this->db->inTransaction()) {
                throw new \RuntimeException('There is no active transaction.');
            }

            $this->db->commit();
        }
    }

    public function afterDelete () {
        if ($this->data['name'] === 'throw_after_delete') {
            throw new \RuntimeException('', 3);
        }

        if ($this->data['name'] === 'delete_commit_transaction') {
            if (!$this->db->inTransaction()) {
                throw new \RuntimeException('There is no active transaction.');
            }

            $this->db->commit();
        }
    }
}