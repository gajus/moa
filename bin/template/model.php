<?php
namespace {{namespace}};

abstract class {{model_name}} extends {{exptends}} {
    const TABLE_NAME = '{{table_name}}';
    const PRIMARY_KEY_NAME = '{{primary_key_name}}';

    static protected
        $columns = {{columns}};
}