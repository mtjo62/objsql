<?php

/**
 * Database access controller class
 *
 * @package objSQL
 * @version 3.9.0
 * @author MT Jordan <mtjo62@gmail.com>
 * @copyright 2016
 * @license zlib/libpng
 * @link http://objsql.sourceforge.net
 */

/**************************************************
 * Included/Required files
 *************************************************/
require_once 'obj_helper.php';

class objSQL extends obj_access
{
    /**********************************************
     * Internal private variables
     *********************************************/

    /**
     * Database connection object
     *
     * @access public
     * @var    mixed
     */
    public $obj_connection = false;

    /**
     * PHP database extension
     *
     * @access private
     * @var    str
     */
    private $obj_db_driver;

    /**
     * Database type
     *
     * @access private
     * @var    str
     */
    private $obj_db_type;

    /**
     * Database type array contains all supported database drivers
     *
     * @access private
     * @var    array
     */
    private $obj_driver_array = ['cubrid'   => ['cubrid', 'pdo_cubrid'],
                                 'firebird' => ['interbase', 'pdo_firebird'],
                                 'mariadb'  => ['mysqli', 'pdo_mysql'],
                                 'mysql'    => ['mysqli', 'pdo_mysql'],
                                 'oracle'   => ['oci8', 'pdo_oci'],
                                 'pgsql'    => ['pgsql', 'pdo_pgsql'],
                                 'sqlite'   => ['sqlite3', 'pdo_sqlite'],
                                 'sqlsrv'   => ['sqlsrv', 'pdo_sqlsrv']];

    /**
     * Helper class instance
     *
     * @access private
     * @var    mixed
     */
    private $obj_helper;
    
    /**
     * Database connection class instance
     *
     * @access private
     * @var    mixed
     */
    private $obj_instance;

    /**
     * Database transaction resource/object
     *
     * @access private
     * @var    mixed
     */
    private $obj_trans_object = false;

    /**
     * objSQL version
     *
     * @access private
     * @var    str
     */
    private $obj_version = '3.9.0';
    
    /**
     * Query argument string vars
     *
     * @access private
     * @var    str
     */
    private $obj_cols;
	private $obj_order_by;
    private $obj_sort_order;
    private $obj_table;
	private $obj_batch_field = false;
	private $obj_batch_id = false;
    private $obj_where;
    
    /**
     * Query argument numeric vars
     *
     * @access private
     * @var    int
     */
    private $obj_limit;
    private $obj_offset;
    
    /**
     * Insert/Update query argument data array var
     *
     * @access private
     * @var    array
     */
    private $obj_data;
    
    /**********************************************
     * Public methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  mixed $datasource
     * @return void 
     */
    public function __construct( $datasource )
    {
        $this->obj_datasource( $datasource );
    }
	
	/**
     * Sets update/delete data array and col argument vars
     *
     * @access public
     * @param  mixed $data
	 * @param  mixed $batch_id
	 * @param  mixed $batch_field
     * @return void 
     */
    public function obj_batch_data( $data, $batch_id, $batch_field=false )
    {
        $this->obj_data = $data; 
		
		//private vars set when performing batch delete/update queries
		$this->obj_batch_id = $batch_id;
		$this->obj_batch_field = $batch_field;
    }
    
    /** 
     * Closes current database connection
     *
     * @access public
     * @return bool
     */
    public function obj_close()
    {
        return $this->obj_instance->obj_db_close();
    }
    
    /** 
     * Sets columns argument var
     *
     * @access public
     * @param  str $cols
     */
    public function obj_cols( $cols )
    {
        $this->obj_cols = $cols;
    }
    
    /**
     * Sets insert/update data array argument var
     *
     * @access public
     * @param  array $data_array
     * @return void 
     */
    public function obj_data( $data_array )
    {
        $this->obj_data = $data_array; 
	}

    /**
     * Executes delete query and returns resultset object/resource
     *
     * @access public
     * @param  str $table
     * @param  str $where
     * @return mixed
     */
    public function obj_delete( $table=false, $where=false )
    {
        //set query arguments
        $this->obj_query_args_set( $table, $where );
        
        //generate query resource
        $query_stmt = new obj_statement( obj_helper::obj_helper_delete( $this->obj_table, $this->obj_where, $this->obj_data, $this->obj_batch_id ), $this, $this->obj_trans_object );
        
        //reset query arguments
        $this->obj_query_args_reset();
        
        //execute & return query result
        return is_object( $query_stmt ) ? $query_stmt->obj_query_execute() : false;
    }
    
    /**
     * Returns error flag for current connection instance - true/false
     *
     * @access public
     * @return bool
     */
    public function obj_error()
    {
        return $this->obj_instance->obj_db_error();
    }

    /**
     * Returns error message for current connection instance
     *
     * @access public
     * @return str
     */
    public function obj_error_message()
    {
        return $this->obj_instance->obj_db_message();
    }

    /**
     * Returns escaped string data for database insertion
     *
     * @access public
     * @param  str $data
     * @return str
     */
    public function obj_escape( $data )
    {
        return $this->obj_instance->obj_db_escape_data( $data );
    }

    /**
     * Returns associative array with general database and script information
     *
     * @access public
     * @return array
     */
    public function obj_info()
    {
        return obj_helper::obj_helper_info( $this->obj_version, $this->obj_db_type, $this->obj_instance->obj_db_info(), $this->obj_db_driver );    
    }

    /**
     * Executes insert query and returns resultset object/resource
     *
     * @access public
     * @param  str   $table
     * @param  array $data_array
     * @return mixed
     */
    public function obj_insert( $table=false, $data_array=false )
    {
        //set query arguments
        $this->obj_query_args_set( $table, false, false, false, false, false, false, $data_array );
        
        //generate query resource
        $query_stmt = new obj_statement( obj_helper::obj_helper_insert( $this->obj_table, $this->obj_data, $this->obj_db_type ), $this, $this->obj_trans_object );

        //reset query arguments
        $this->obj_query_args_reset();
        
        //execute & return query result
        return is_object( $query_stmt ) ? $query_stmt->obj_query_execute() : false;
    }
    
    /**
     * Sets limit argument var
     *
     * @access public
     * @param  int $limit
     * @return void 
     */
    public function obj_limit( $limit )
    {
        $this->obj_limit = $limit;
    }
    
    /**
     * Sets offset argument var
     *
     * @access public
     * @param  int $offset
     * @return void 
     */
    public function obj_offset( $offset )
    {
        $this->obj_offset = $offset;
    }
    
    /**
     * Sets order by argument var
     *
     * @access public
     * @param  str $order_by
     * @return void 
     */
    public function obj_order_by( $order_by )
    {
        $this->obj_order_by = $order_by;
    }

    /**
     * Executes select paging query and returns resultset object/resource and number of pages via $limit
     *
     * @access public
     * @param  str $table
     * @param  str $cols
     * @param  str $where
     * @param  str $order_by
     * @param  int $limit
     * @param  int $offset
     * @return mixed
     */
    public function obj_paging( $table=false, $cols=false, $where=false, $order_by=false, $limit=1, $offset=1 )
    {
        //set query arguments
        $this->obj_query_args_set( $table, $cols, $where, $order_by, false, $limit, $offset );
        
        //generate query string, last page and query resource
        $obj_paging = obj_helper::obj_helper_paging( $this->obj_cols, $this->obj_where, $this->obj_order_by, $this->obj_limit, $this->obj_offset );
        $query_stmt = new obj_statement( $this->obj_instance->obj_db_paging( $this->obj_table, $obj_paging[0], $obj_paging[1], $obj_paging[2], $obj_paging[3], $obj_paging[4] ), $this, $this->obj_trans_object );
        $obj_last_page = ceil( $this->obj_row_count( $this->obj_table, $this->obj_cols, $this->obj_where ) / $this->obj_limit );
        
        //reset query arguments
        $this->obj_query_args_reset();
        
        //execute & return query result
        return is_object( $query_stmt ) ? [$query_stmt->obj_query_execute(), $obj_last_page] : false;
    }

    /**
     * Returns prepared statement instance
     *
     * @access public
     * @param  str   $query
     * @param  array $param_vars
     * @return mixed
     */
    public function obj_prepare_statement( $query, $param_vars=false )
    {
        //generate query resource
        $query_stmt = new obj_prepare( $query, $this, $param_vars, $this->obj_trans_object );

        //return query resource
        return is_object( $query_stmt ) ? $query_stmt : false;
    }

    /**
     * Executes non-prepared query and returns resultset object/resource
     *
     * @access public
     * @param  str $query
     * @return mixed
     */
    public function obj_query( $query )
    {
        //generate query resource
        $query_stmt = new obj_statement( $query, $this, $this->obj_trans_object );

        //execute & return query result
        return is_object( $query_stmt ) ? $query_stmt->obj_query_execute() : false;
    }
 
    /**
     * Executes select count query and returns row count
     *
     * @access public
     * @param  str $table
     * @param  str $cols
     * @param  str $where
     * @return int
     */
    public function obj_row_count( $table=false, $cols=false, $where=false )
    {
        //set query arguments
        $this->obj_query_args_set( $table, $cols, $where );
        
        //generate query arguments
        $obj_row_count_args = obj_helper::obj_helper_row_count( $this->obj_cols, $this->obj_where );
        
        //execute rowcount
        $obj_row_count = $this->obj_instance->obj_db_rowcount( $this->obj_table, $obj_row_count_args[0], $obj_row_count_args[1] );
        
        //reset query arguments
        $this->obj_query_args_reset();
        
        //return rowcount
        return $obj_row_count;
    }

    /**
     * Executes select query and returns resultset object/resource
     *
     * @access public
     * @param  str $table
     * @param  str $cols
     * @param  str $where
     * @param  str $order_by
     * @param  str $sort_order
     * @return mixed
     */
    public function obj_select( $table=false, $cols=false, $where=false, $order_by=false, $sort_order=false )
    {
        //set query arguments
        $this->obj_query_args_set( $table, $cols, $where, $order_by, $sort_order );
        
        //generate query resource
        $query_stmt = new obj_statement( obj_helper::obj_helper_select( $this->obj_table, $this->obj_cols, $this->obj_where, $this->obj_order_by, $this->obj_sort_order ), $this, $this->obj_trans_object );
        
        //reset query arguments
        $this->obj_query_args_reset();
        
        //execute & return query result
        return is_object( $query_stmt ) ? $query_stmt->obj_query_execute() : false;
    }
    
    /**
     * Sets sort order argument var
     *
     * @access public
     * @param  str $sort_order
     * @return void 
     */
    public function obj_sort_order( $sort_order )
    {
        $this->obj_sort_order = $sort_order;
    }
    
    /**
     * Sets table argument var
     *
     * @access public
     * @param  str $table
     * @return void 
     */
    public function obj_table( $table )
    {
        $this->obj_table = $table;
    }

    /**
     * Begins transaction
     *
     * @access public
     * @return mixed
     */
    public function obj_transaction()
    {
        //begin transaction
        $obj_transaction = new obj_transaction( $this->obj_connection );
        //$this->obj_trans_object = ( $this->obj_db_type === 'firebird' ) ? $obj_transaction->obj_trans_object : true;
        $this->obj_trans_object = ( is_object( $obj_transaction ) ) ?: true;

        //return transaction object
        return is_object( $obj_transaction ) ? $obj_transaction : false;
    }

    /**
     * Executes update query and returns resultset object/resource
     *
     * @access public
     * @param  str   $table
     * @param  array $data_array
     * @param  str   $where
     * @return mixed
     */
    public function obj_update( $table=false, $data_array=false, $where=false )
    {
        //set query arguments
        $this->obj_query_args_set( $table, false, $where, false, false, false, false, $data_array );
		
		//generate query resource
        $query_stmt = new obj_statement( obj_helper::obj_helper_update( $this->obj_table, $this->obj_data, $this->obj_where, $this->obj_batch_id, $this->obj_batch_field ), $this, $this->obj_trans_object );
        
        //reset query arguments
        $this->obj_query_args_reset();
        
        //execute & return query result
        return is_object( $query_stmt ) ? $query_stmt->obj_query_execute() : false;
    }
   
    /**
     * Sets where argument var
     *
     * @access public
     * @param  str $where
     * @return void 
     */
    public function obj_where( $where )
    {
        $this->obj_where = $where;
    }

    /**********************************************
     * Private methods
     *********************************************/

    /**
     * Sets database connection instance/vars and loads database drivers
     *
     * @access private
     * @param  mixed $datasource
     * @return mixed
     */
    private function obj_datasource( $datasource )
    {
        $obj_return = false;
        $obj_datasource = ( is_array( $datasource ) ) ? $datasource : explode( ',', $datasource );

        //if < objSQL 3.5.0 clean up string for backwards compatibility
        $this->obj_db_type = str_replace( 'sqlite3', 'sqlite', str_replace( 'pdo:', '', strtolower( $obj_datasource[0] ) ) );
        $this->obj_db_driver = obj_helper::obj_helper_datasource( $obj_datasource, $this->obj_db_type, $this->obj_driver_array );
        
        if ( $this->obj_db_driver !== false )
        {
            //if valid driver, generate connection instance
            $this->obj_instance = new obj_connection( $obj_datasource );
            $obj_return = ( $this->obj_instance ) ? $this->obj_connection = $this->obj_instance->obj_db_connection() : false;
        }
       
        return $obj_return;
    }
    
    /**
     * Set query arguments
     *
     * @access private
     * @param  str   $table
     * @param  str   $cols
     * @param  str   $where
     * @param  str   $order_by
     * @param  str   $sort_order
     * @param  int   $limit
     * @param  int   $offset
     * @param  array $data_array 
     * @return void 
     */
    private function obj_query_args_set( $table=false, $cols=false, $where=false, $order_by=false, $sort_order=false, $limit=1, $offset=1, $data_array=false )
    {
        $this->obj_cols = ( isset( $this->obj_cols ) ) ? $this->obj_cols : $cols;
        $this->obj_data = ( isset( $this->obj_data ) ) ? $this->obj_data : $data_array;
        $this->obj_limit = ( isset( $this->obj_limit ) ) ? $this->obj_limit : $limit;
        $this->obj_offset = ( isset( $this->obj_offset ) ) ? $this->obj_offset : $offset;
        $this->obj_order_by = ( isset( $this->obj_order_by ) ) ? $this->obj_order_by : $order_by;
        $this->obj_sort_order = ( isset( $this->obj_sort_order ) ) ? $this->obj_sort_order : $sort_order;
        $this->obj_table = ( isset( $this->obj_table ) ) ? $this->obj_table : $table;
        $this->obj_where = ( isset( $this->obj_where ) ) ? $this->obj_where : $where;
    }
    
    /**
     * Resets query arguments
     *
     * @access private
     * @return void
     */
    private function obj_query_args_reset()
    {
        $this->obj_cols = null;
        $this->obj_data = null;
        $this->obj_order_by = null;
        $this->obj_sort_order = null;
        $this->obj_table = null;
        $this->obj_where = null;
		$this->obj_batch_id = null;
		$this->obj_batch_field = null;
    }
    
    /*****************************************************************************************************
     * Abstract protected methods - prevents calling these methods outside of the objSQL controller class
     *****************************************************************************************************/

    /**
     * Protected methods from extended connection classes
     *
     * @access protected
     */
    protected function obj_db_close() {}
    protected function obj_db_connection() {}
    protected function obj_db_error() {}
    protected function obj_db_escape_data( $str=null ) {}
    protected function obj_db_message() {}
    protected function obj_db_info() {}
    protected function obj_db_paging( $table, $cols=false, $where=false, $order_by=false, $limit=1, $offset=1 ) {}
    protected function obj_db_rowcount( $table, $cols=false, $where=false ) {}
}

/* EOF objSQL.php */
/* Location: ./objSQL.php */