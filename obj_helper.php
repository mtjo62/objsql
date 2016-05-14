<?php

/**
 * objSQL auxillary helper class
 *
 * @package objSQL
 * @version 3.9.0
 * @author MT Jordan <mtjo62@gmail.com>
 * @copyright 2016
 * @license zlib/libpng
 * @link http://objsql.sourceforge.net
 */

class obj_helper
{
    /**********************************************
     * Public methods
     *********************************************/
    
    /**
     * Check if PHP extension is enabled and return driver type or false on failure
     *
     * @access public
     * @param  array  $datasource
     * @param  str    $db_type
     * @param  array  $driver_array
     * @return mixed
     */
    public static function obj_helper_datasource( $datasource, $db_type, $driver_array )
    {
        $obj_return = false;
        $obj_driver = self::obj_helper_driver( $db_type, $driver_array );

        //verify db type
        if ( array_key_exists( $db_type, $driver_array ) )
        {
            if ( $obj_driver !== false )
                $obj_return = $obj_driver;
            else
                //throw error if extension(s) isn't enabled
                trigger_error( "$db_type extensions are not enabled.", \E_USER_WARNING);
        }
        else
        {
            //throw error if db type invalid
            trigger_error( "Unsupported database type: $datasource[0].", \E_USER_WARNING);
        }

        return $obj_return;
    } 

    /**
     * Returns delete query string
     *
     * @access public
     * @param str $table
     * @param str $where
     * @return str
     */
    public static function obj_helper_delete( $table, $where, $data=false, $batch_id=false )
    {
        $query_where = '';
		
        if ( !$data || !$batch_id )
        {
            $query_where = ( !trim( $where ) ) ? '' : "WHERE $where";
        }
		else
        {
            $delete_data = ( is_array( $data ) ) ? implode( ',', $data ) : $data;
            
			$query_where = "WHERE $batch_id IN ($delete_data)";
        }
		
        return "DELETE FROM $table $query_where";
    }

    /**
     * Returns associative array with general database and script information
     *
     * @access public
     * @param  str   $version
     * @param  str   $db_type
     * @param  array $instance
     * @param  str   $driver
     * @return array
     */
    public static function obj_helper_info( $version, $db_type, $instance, $driver )
    {
        return ['OBJSQL_VERSION'   => $version,
                'DATABASE_NAME'    => $instance[2],
                'DATABASE_TYPE'    => $db_type,
                'DATABASE_DRIVER'  => "php_$driver",
                'DATABASE_VERSION' => $instance[0],
                'DATABASE_CHARSET' => $instance[1],
                'PHP_VERSION'      => phpversion()];
    }

    /**
     * Returns insert query string
     *
     * @access public
     * @param  str   $table
     * @param  array $data_array
     * @param  str   $db_type
     * @return str
     */
    public static function obj_helper_insert( $table, $data_array, $db_type )
    {
		$query_sql = '';
		
        //$data_array MUST be a key value pair array 
        //value can be a one dim array or comma delimited string
        $array_keys = array_keys( $data_array );
        $array_vals = array_values( $data_array );

        //if array_vals[$i] is a string, convert to array
        for ( $i = 0; $i < count( $array_vals ); $i++ )
        {
            if ( !is_array( $array_vals[$i] ) )
            {
                $temp_array = explode( ',', $array_vals[$i] );
                $array_vals[$i] = $temp_array;
            }
        }
        
        //check for batch insert if count > 1
        $array_vals_cnt = count( $array_vals[0] );
        
        for ( $i = 0; $i < count( $array_vals[0] ); $i++ )
        {
            $query_insert[] = '(';

            for ( $j = 0; $j < count( $array_vals ); $j++ )
            {
                if ( is_string( $array_vals[$j][$i] ) && !is_numeric( $array_vals[$j][$i] ) )
					$query_insert[] = "'{$array_vals[$j][$i]}'";
                else
                    $query_insert[] = $array_vals[$j][$i];
            }

            $query_insert[] = ')';
        }
        
        $query_cols = implode( ',', $array_keys );
        $query_vals = str_replace([',),(,', ',)', '(,'], ['),(', ')', '('], implode( ',', $query_insert ) );

        if ( $array_vals_cnt > 1 && ( $db_type == 'firebird' || $db_type == 'oracle' ) )
			$query_sql = self::obj_insert_sql( $query_cols, $query_vals, $db_type, $table );
        else
            $query_sql = "INSERT INTO $table ($query_cols) VALUES $query_vals";
   
		return $query_sql;
    }

    /**
     * Returns paging query vars
     *
     * @access public
     * @param  str $cols
     * @param  str $where
     * @param  str $order_by
     * @param  int $limit
     * @param  int $offset
     * @return array
     */
    public static function obj_helper_paging( $cols, $where, $order_by, $limit, $offset )
    {
        //make sure $limit & $offset are unsigned ints > 0
        $set_offset = ( is_numeric( $offset ) && $offset > 0 ) ? (int)$offset : 1;
        $set_limit = ( is_numeric( $limit ) && $limit > 0 ) ? (int)$limit : 1;
        $query_cols  = ( !trim( $cols ) ) ? '*' : $cols;
        $query_where = ( !trim( $where ) ) ? '' : "WHERE $where";
        $query_order = ( !trim( $order_by ) ) ? '' : "ORDER BY $order_by";
        $query_offset = ( $set_offset - 1 ) * $set_limit;
        
        return [$query_cols, $query_where, $query_order , $set_limit, $query_offset];
    }

    /**
     * Returns row count query vars
     *
     * @access public
     * @param  str $cols
     * @param  str $where
     * @return array
     */
    public static function obj_helper_row_count( $cols, $where )
    {
        $query_cols  = ( !trim( $cols ) ) ? '*' : $cols;
        $query_where = ( !trim( $where ) ) ? '' : "WHERE $where";

        return [$query_cols, $query_where];
    }

    /**
     * Returns select query string
     *
     * @access public
     * @param  str $table
     * @param  str $cols
     * @param  str $where
     * @param  str $order_by
     * @param  str $sort_order
     * @return str
     */
    public static function obj_helper_select( $table, $cols, $where, $order_by, $sort_order )
    {
        $query_cols  = ( !trim( $cols ) ) ? '*' : $cols;
        $query_where = ( !trim( $where ) ) ? '' : "WHERE $where";
        $query_order = ( !trim( $order_by ) ) ? '' : "ORDER BY $order_by";
        $query_sort  = ( !trim( $sort_order ) && strtolower( $sort_order ) != 'desc' && strtolower( $sort_order ) != 'asc' ) ? '' : $sort_order;

        return "SELECT $query_cols FROM $table $query_where $query_order $query_sort";
    }

    /**
     * Returns update query string
     *
     * @access public
     * @param  str     $table
     * @param  array   $data_array
     * @param  str     $where
	 * @param  mixed   $batch_id
	 * @param  mixed   $batch_field
     * @return str
     */
    public static function obj_helper_update( $table, $data_array, $where, $batch_id, $batch_field )
    {
		$obj_return = '';
		
        if ( !$batch_id && !$batch_field )
		{
			//$data_array MUST be a key value pair array
			$query_cols = array_keys( $data_array );
			$query_vals = array_values( $data_array );
			$query_where = ( !trim( $where ) ) ? '' : "WHERE $where";
			$query_update = '';

			for ( $i = 0; $i < count( $data_array ); $i++ )
			{
				if ( is_string( $query_vals[$i] ) && !is_numeric( $query_vals[$i] ) )
					$query_update .= "{$query_cols[$i]}='{$query_vals[$i]}',";
				else
					$query_update .= "{$query_cols[$i]}={$query_vals[$i]},";
			}

			$query_vars = rtrim( $query_update, ',' );

			$obj_return = "UPDATE $table SET $query_vars $query_where";
		}
		else
		{
			//treat as batch process
			$obj_return = self::obj_update_sql( $data_array, $batch_id, $batch_field, $table );
		}
				
		return $obj_return;
    }
    
    /**********************************************
     * Private methods
     *********************************************/
    
    /**
     * Validate and load driver extension or false on failure
     *
     * @access private
     * @param str   $db_type
     * @param array $driver_array
     * @return mixed
     */
    private static function obj_helper_driver( $db_type, $driver_array )
    {
        $obj_return = false;
        $obj_db_type = ( $db_type === 'mariadb' ) ? 'mysql' : $db_type;

        if ( extension_loaded ( $driver_array[$obj_db_type][1] ) )
        {
            //load PDO driver if enabled
            $obj_return = $driver_array[$obj_db_type][1];

            require_once "drivers/$obj_db_type/pdo_driver.php";
        }
        elseif ( extension_loaded ( $driver_array[$obj_db_type][0] ) )
        {
            //load standard driver if enabled
            $obj_return = $driver_array[$obj_db_type][0];

            require_once "drivers/$obj_db_type/driver.php";
        }

        return $obj_return;
    }
    
    /**
     * Set SQL statement for batch inserts for firebird or oracle
     *
     * @access private
     * @param str $query_cols
     * @param str $data
     * @param str $db_type
     * @param str $table
     * @return str
     */
    private static function obj_insert_sql( $query_cols, $data, $db_type, $table )
    {
        $query_vals = explode( '~:^', ( str_replace( '),(', ')~:^(', $data ) ) );
        $query_sql = ( $db_type == 'firebird' ) ? 'EXECUTE BLOCK AS BEGIN ' : 'INSERT ALL ';
        
        for ( $i = 0; $i < count( $query_vals ); $i++ )
        {
            if ( $db_type == 'firebird' )
                $query_sql .= "INSERT INTO $table ($query_cols) VALUES $query_vals[$i];";
            else 
                $query_sql .= "INTO $table ($query_cols) VALUES $query_vals[$i] ";
        }
        
        $query_sql .= ( $db_type == 'firebird' ) ? ' END' : ' SELECT * FROM DUAL';
        
        return $query_sql;
    }
	
	/**
     * Set SQL statement for batch updates
     *
     * @access private
     * @param array $data_array
     * @param mixed $batch_id
     * @param mixed $batch_field
     * @return str
     */
    private static function obj_update_sql( $data_array, $batch_id, $batch_field, $table )
    {
		$query_sql = "UPDATE $table SET $batch_field = CASE $batch_id ";
		$query_cols = array_keys( $data_array );
		$query_vals = array_values( $data_array );
               
        for ( $i = 0; $i < count( $query_vals ); $i++ )
        {
			if ( is_string( $query_vals[$i] ) && !is_numeric( $query_vals[$i] ) )
				$query_sql .= "WHEN $query_cols[$i] THEN '{$query_vals[$i]}' ";
			else
				$query_sql .= "WHEN $query_cols[$i] THEN $query_vals[$i] ";
        }
		
		$query_sql .= "END WHERE $batch_id IN (" . implode( ',', $query_cols ) . ")";
		
		return $query_sql;
	}
}

/**
 * Protected methods from extended connection classes
 *
 * @access protected
 */

abstract class obj_access
{
    /**
     * Abstract protected methods from extended connection classes
     *
     * @access protected
     */
    abstract protected function obj_db_close();
    abstract protected function obj_db_connection();
    abstract protected function obj_db_error();
    abstract protected function obj_db_escape_data( $str );
    abstract protected function obj_db_message();
    abstract protected function obj_db_info();
    abstract protected function obj_db_paging( $table, $cols, $where, $order_by, $limit, $offset );
    abstract protected function obj_db_rowcount( $table, $cols, $where );
}

/* EOF obj_helper.php */
/* Location: ./obj_helper.php */