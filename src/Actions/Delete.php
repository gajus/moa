<?php

namespace Gajus\MOA\Actions;

use Gajus\MOA\Exception;

trait Delete {

    /**
     * Delete object from the database. Deleted object retains its data except for the primary key value.
     *
     * @return $this
     */
    public function delete(){

        if (!isset($this->data[static::PRIMARY_KEY_NAME])) {
            return $this;
        }

        $this->startSqlForDeleteMethod();
        $this->runAfterDelete();
        $this->commitDelete();

        unset($this->data[static::PRIMARY_KEY_NAME]);

        return $this;
    }

    /**
     * Start transaction and execute Sql
     *
     * @return bool
     */
    protected function startSqlForDeleteMethod(){
        $this->db
            ->beginTransaction();

        $this->db
            ->prepare("DELETE FROM `" . static::TABLE_NAME . "` WHERE `" . static::PRIMARY_KEY_NAME . "` = ?")
            ->execute([$this->data[static::PRIMARY_KEY_NAME]]);

        return true;
    }

    /**
     * Try call afterDelete method and if it's false , rollback transaction
     *
     * @return bool
     * @throws \Exception
     */
    protected  function runAfterDelete()
    {
        try {
            $this->afterDelete();
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        return true;
    }

    /**
     * Check and commit transaction
     *
     * @return mixed
     * @throws \Gajus\MOA\Exception\LogicException
     */
    protected function commitDelete(){
        if (!$this->db->inTransaction()) {
            throw new Exception\LogicException('Transaction was commited before the time.');
        }

        return $this->db->commit();
    }


} 