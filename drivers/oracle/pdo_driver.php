<?php

/**
 * Oracle PDO database access classes
 *
 * @package objSQL
 * @version 3.9.0
 * @author MT Jordan <mtjo62@gmail.com>
 * @copyright 2016
 * @license zlib/libpng
 * @link http://objsql.sourceforge.net
 */


/*************************************************************************************************************
 * Begin database connection/utility class
 ************************************************************************************************************/


class obj_connection extends obj_access
{
    /**********************************************
     * Internal variables
     *********************************************/

    /**
     * Database connection object
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection = null;

    /**
     * Database connection information
     *
     * @access private
     * @var array
     */
    private $obj_datasource;

	/**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  array $datasource
     */
    public function __construct( $datasource )
    {
        $this->obj_datasource = $datasource;
    }

    /**
     * Returns database connection object
     *
     * @access protected
     * @return mixed
     */
    protected function obj_db_connection()
    {
        $obj_port = ( array_key_exists( 5, $this->obj_datasource ) ) ? (int)$this->obj_datasource[5] : false;

        if ( $obj_port )
        {
            $obj_DSN = "oci:dbname=//{$this->obj_datasource[1]}:$obj_port/{$this->obj_datasource[4]}";
        }
		else
        {
            $obj_DSN = "oci:dbname=//{$this->obj_datasource[1]}:/{$this->obj_datasource[4]}";
        }

		$obj_connection = new PDO( $obj_DSN, $this->obj_datasource[2], $this->obj_datasource[3] );

		return ( is_object( $obj_connection ) ) ? $this->obj_connection = $obj_connection : false;
	}

    /**
     * Closes connection to database server
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_close()
    {
        return $this->obj_connection = null;
    }

	/**
     * Returns error flag for current connection instance
     *
     * @access protected
     * @return bool
     */
    protected function obj_db_error()
    {
        return ( $this->obj_connection->errorInfo()[1] != '' ) ? true : false;
	}

    /**
     * Escapes string data for database insertion
     *
     * @access protected
     * @param  str $data
     * @return str
     */
    protected function obj_db_escape_data( $data )
    {
		return str_replace( "'", "''", $data );
    }

    /**
     * Returns error message for current connection instance
     *
     * @access protected
     * @return mixed
     */
    protected function obj_db_message()
    {
        return ( $this->obj_db_error() ) ? $this->obj_connection->errorInfo()[2] : null;
    }

    /**
     * Returns database server information
     *
     * @access protected
     * @return array
     */
    protected function obj_db_info()
    {
		$obj_get_info = $this->obj_connection->query( "SELECT USERENV ('language') FROM DUAL" );

		return [$this->obj_connection->getAttribute( constant( 'PDO::ATTR_SERVER_VERSION' ) ), $obj_get_info->fetch( PDO::FETCH_NUM )[0], $this->obj_datasource[4]];
    }

    /**
     * Returns select paging query SQL statement
     *
     * @access protected
     * @param  str $table
     * @param  str $cols
     * @param  str $where
     * @param  str $order_by
     * @param  int $limit
     * @param  int $offset
     * @return str
     */
    protected function obj_db_paging( $table, $cols=false, $where=false, $order_by=false, $limit=1, $offset=1 )
    {
        $limit_str = ( $offset > 0 ) ? ( $offset + $limit ) - 1 : $offset + $limit;

        return "SELECT * FROM ( SELECT rownum rnum, a.*
                FROM (
                SELECT $cols
                FROM $table
                $where $order_by ) a
                WHERE rownum <= $limit_str )
                WHERE rnum >= $offset";
    }

	/**
     * Returns row count for named table with arguments
	 * Returns -1 if undetermined or failure
     *
     * @access protected
	 * @param  str $table
     * @param  str $cols
     * @param  str $where
     * @return int
     */
    protected function obj_db_rowcount( $table, $cols=false, $where=false )
    {
        //default return value
        $obj_return = -1;

		$query_stmt = $this->obj_connection->query( "SELECT COUNT($cols) FROM $table $where" );

		if ( is_object( $query_stmt ) )
		{
			$rowcount = $query_stmt->fetchColumn();
			$obj_return = ( $rowcount >= 0 ) ? $rowcount : -1;
            
            $query_stmt = null;
		}

        return $obj_return;
	}
}


/*************************************************************************************************************
 * Begin database statement class
 ************************************************************************************************************/


class obj_statement
{
    /**********************************************
     * Internal variables
     *********************************************/

    /**
     * Database connection object
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection;

	/**
     * Query string
     *
     * @access private
     * @var    str
     */
    private $obj_query;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  str   $query
     * @param  mixed $connection
     */
    function __construct( $query, $connection )
    {
        $this->obj_connection = $connection->obj_connection;
        $this->obj_query = $query;
    }

    /**
     * Executes general query and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_query_execute()
    {
        $query_stmt = $this->obj_connection->query( $this->obj_query );

        return ( is_object( $query_stmt ) ) ? new obj_resultset( $query_stmt ) : false;
    }
 }


/*************************************************************************************************************
 * Begin database prepared statement class
 ************************************************************************************************************/


class obj_prepare
{
    /**********************************************
     * Internal variables
     *********************************************/

    /**
     * Database connection object
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection;

	/**
     * Set obj_bind parameter counter
     *
     * @access private
     * @var    int
     */
    private $obj_parameter_cnt = 0;

   /**
     * Prepared statement instance
     *
     * @access private
     * @var    bool
     */
    private $obj_prepare_instance;

    /**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  str   $query
     * @param  mixed $connection
	 */
    function __construct( $query, $connection )
    {
        $this->obj_connection = $connection->obj_connection;
        $this->obj_prepare_init( $query );
    }

    /**
     * Sets parameters and parameter types for prepared statement
     *
     * @access public
     * @param  mixed $param
     */
    public function obj_bind( $param )
    {
        $this->obj_parameter_cnt++;

        return $this->obj_prepare_instance->bindParam( $this->obj_parameter_cnt, $param );
    }

    /**
     * Destroys prepared statement object
     *
     * @access public
     * @return bool
     */
    public function obj_close_statement()
    {
        $this->obj_parameter_cnt = 0;
        $this->obj_prepare_instance->closeCursor();

		return ( !is_object( $this->obj_prepare_instance = null ) ) ? true : false;
    }

    /**
     * Binds parameters, executes prepared statement and returns resultset object
     *
     * @access public
     * @return mixed
     */
    public function obj_execute()
    {
        $query_stmt = $this->obj_prepare_instance->execute();

        return ( $query_stmt ) ? new obj_resultset( $this->obj_prepare_instance ) : false;
    }

    /**
     * Frees resultset memory from prepared statement object and resets binding parameters
     *
     * @access public
     * @return bool
     */
    public function obj_free_statement()
    {
        $this->obj_parameter_cnt = 0;

        return true;
    }

    /**
     * Returns prepared statement instance
     *
     * @access private
     * @param  str $query
     * @return bool
     */
    private function obj_prepare_init( $query )
    {
        return $this->obj_prepare_instance = $this->obj_connection->prepare( $query );
    }
}


/*************************************************************************************************************
 * Begin database resultset class
 ************************************************************************************************************/


class obj_resultset
{
    /**********************************************
     * Internal variables
     *********************************************/

	/**
     * Query record
     *
     * @access private
     * @var    array
     */
    private $obj_record = [];

    /**
     * Query resultset object
     *
     * @access private
     * @var    mixed
     */
    private $obj_result = false;

    /**********************************************
     * Class methods
     *********************************************/
    /**
     * Constructor
     *
     * @access public
     * @param  mixed $result
     */
    public function __construct( $result )
    {
		$this->obj_result = $result;
    }

    /**
     * Returns number of affected rows from insert/delete/update query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_affected_rows()
    {
        $affected_rows = $this->obj_result->rowCount();

        return ( $affected_rows >= 0 ) ? $affected_rows : -1;
    }

    /**
     * Returns resultset object as associative array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_assoc()
    {
        $result = $this->obj_result->fetch( PDO::FETCH_ASSOC );

        return ( is_array( $result ) && $result !== false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset object as numeric array
     *
     * @access public
     * @return mixed
     */
    public function obj_fetch_num()
    {
        $result = $this->obj_result->fetch( PDO::FETCH_NUM );

        return ( is_array( $result ) && $result !== false ) ? $this->obj_record = $result : null;
    }

	/**
     * Returns resultset object as object
     *
     * @access public
     * @return mixed
     */

    public function obj_fetch_object()
    {
        $result = $this->obj_result->fetch( PDO::FETCH_OBJ );

        return ( is_object( $result ) && $result !== false ) ? $this->obj_record = $result : null;
    }

    /**
     * Returns resultset record
     *
     * @access public
     * @param  mixed $field
     * @return mixed
     */
    public function obj_field( $field )
    {
        if ( $this->obj_result )
		{
			//get_magic_quotes deprecated in php 5.4 - added for backwards compatibility
			return ( get_magic_quotes_runtime() ) ? stripslashes( $this->obj_record[strtoupper( $field )] ) : $this->obj_record[strtoupper( $field )];
		}
    }

    /**
     * Frees resultset memory and destroys resultset object
     *
     * @access public
     * @return bool
     */
    public function obj_free_result()
    {
        $this->obj_record = [];

        return ( !is_object( $this->obj_result = null ) ) ? true : false;
    }

    /**
     * Returns number of fields from query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_num_fields()
    {
        $num_cols = $this->obj_result->columnCount();

        return ( $num_cols >= 0 ) ? $num_cols : -1;
    }

   /**
     * Returns number rows from query
     * Returns -1 if undetermined or failure
     *
     * @access public
     * @return int
     */
    public function obj_num_rows()
    {
		$num_rows = count( $this->obj_result->fetchAll( PDO::FETCH_NUM ) );

        return ( $num_rows >= 0 ) ? $num_rows : -1;
	}
}


/*************************************************************************************************************
 * Begin database transaction class
 ************************************************************************************************************/


class obj_transaction
{
	/**********************************************
     * Internal variables
     *********************************************/

	/**
     * Database connection instance
     *
     * @access private
     * @var    mixed
     */
    private $obj_connection;

	/**********************************************
     * Class methods
     *********************************************/

    /**
     * Constructor
     *
     * @access public
     * @param  mixed $connection
     */
    public function __construct( $connection )
    {
        $this->obj_connection = $connection;

        //turn off autocommit
		$this->obj_connection->beginTransaction();
	}

    /**
     * Commits transaction for current transaction instance
     *
     * @access public
     * @return bool
     */
    public function obj_commit()
    {
		return $this->obj_connection->commit();
    }

    /**
     * Rollbacks transaction for current transaction instance
     *
     * @access public
     * @param  str $savepoint
     * @return bool
     */
    public function obj_rollback( $savepoint=false )
    {
        if ( !$savepoint )
        {
            $rollback = $this->obj_connection->rollBack();
        }
        else
        {
            $rollback = $this->obj_connection->query( "ROLLBACK TO SAVEPOINT $savepoint" );
        }

        return ( $rollback || is_object( $rollback ) ) ? true : false;
    }

    /**
     * Creates transaction savepoint for current transaction instance
     *
     * @access public
     * @param  str $savepoint
     * @return bool
     */
    public function obj_savepoint( $savepoint )
    {
		return ( is_object( $this->obj_connection->query( "SAVEPOINT $savepoint" ) ) ) ? true : false;
    }
}

/* EOF pdo_driver.php */
/* Location: ./drivers/oracle/pdo_driver.php */