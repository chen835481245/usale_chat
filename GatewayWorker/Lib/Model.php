<?php
/////////////////////////////////////////////////////////////////
// SpeedPHP中文PHP框架, Copyright (C) 2008 - 2010 SpeedPHP.com //
/////////////////////////////////////////////////////////////////
namespace GatewayWorker\Lib;
/**
 * db_mysql MySQL数据库的驱动支持
 */
class Model {
	/**
	 * 数据库链接句柄
	 */
	public $conn;
	/**
	 * 执行的SQL语句记录
	 */
	public $arrSql;
	/**
	 * 表主键
	 */
	public $pk;
	/**
	 * 表全名
	 */
	public $tbl_name = null;
    public $dbConfig=array();

	/**
	 * 构造函数
	 *
	 * @param dbConfig  数据库配置
	 */
	public function __construct()
	{
        $this->dbConfig=array(
            'driver' => 'mysql',   // 驱动类型
            'host' => 'develop.19baba.com', // 数据库地址
            'port' => 3306,        // 端口
            'login' => 'hzxq',     // 用户名
            'password' => "xsdc311ch",      // 密码
            'database' => 'usale'
        );

		$linkfunction = 'mysqli_connect';//( TRUE == $dbConfig['persistent'] ) ? 'mysqli_pconnect' : 'mysqli_connect';
		$this->conn = $linkfunction($this->dbConfig['host'].":".$this->dbConfig['port'], $this->dbConfig['login'], $this->dbConfig['password']);
		if(!$this->conn){
			die("数据库链接错误 : " . iconv('gbk','utf-8',mysqli_connect_error()));
		}
		mysqli_select_db($this->conn,$this->dbConfig['database']) or die("无法找到数据库，请确认数据库名称正确！");
	}
	public function table($table)
	{
		$this->tbl_name=$table;
		return $this;
	}
	public function link($host,$port,$login,$password,$database)
	{
		$linkfunction = 'mysqli_connect';
		$this->conn = $linkfunction($host.":".$port, $login, $password) or die("数据库链接错误 : " . iconv('gbk','utf-8',mysqli_error()));
		mysqli_select_db($this->conn,$database) or die("无法找到数据库，请确认数据库名称正确！");
	}
	/**
	 * 为处理多数据库所以需要重置连接
	 */
	public function connect($database)
	{
		mysqli_select_db($this->conn,$database) or die("无法找到数据库，请确认数据库名称正确！");
	}
	/**
	 * 按SQL语句获取记录结果，返回数组
	 *
	 * @param sql  执行的SQL语句
	 */
	public function getAll($sql)
	{
		/*if( ! $result = $this->exec($sql) )return array();
		 if( ! mysqli_num_rows($result) )return array();
		 $rows = array();
		 while($rows[] = mysqli_fetch_array($result,MYSQL_ASSOC)){}
		 mysqli_free_result($result);
		 array_pop($rows);
		 return $rows;*/

		if( ! $result = $this->conn->query($sql) )return array();
		if( ! mysqli_num_rows($result) )return array();
		$rows = array();
		$count=0;
			
		$infoFields=$result->fetch_fields();
		/*foreach ($infoFields as $val) {
			printf("Name:     %s\n", $val->name);
			printf("Table:    %s\n", $val->table);
			printf("max. Len: %d\n", $val->max_length);
			printf("Flags:    %d\n", $val->flags);
			printf("Type:     %d\n\n", $val->type);
			}*/

		while($rowObj=mysqli_fetch_array($result,MYSQL_ASSOC)){
			foreach ($infoFields as $val) {
				//print_r($val->type.'='.$rowObj[$val->name].' ');
				switch($val->type)
				{
					case MYSQLI_TYPE_TINY:
						$rowObj[$val->name]=(int)$rowObj[$val->name];
						break;
					case MYSQLI_TYPE_SHORT:
						$rowObj[$val->name]=(int)$rowObj[$val->name];
						break;
					case MYSQLI_TYPE_LONG:
						$rowObj[$val->name]=(int)$rowObj[$val->name];
						break;
					case MYSQLI_TYPE_FLOAT:
						$rowObj[$val->name]=(float)$rowObj[$val->name];
						break;
					case MYSQLI_TYPE_DOUBLE:
						$rowObj[$val->name]=(double)$rowObj[$val->name];
						break;
					case MYSQLI_TYPE_LONGLONG:
						$rowObj[$val->name]=(int)$rowObj[$val->name];
						break;
					case MYSQLI_TYPE_INT24:
						{
							$rowObj[$val->name]=(int)$val->value;
						}
						break;
				}
				if(!isset($rowObj[$val->name])){
					$rowObj[$val->name]='';
				}
			}
			$rows[] = $rowObj;
		}
		mysqli_free_result($result);
		//array_pop($rows);
		//die('exit');
		return $rows;
	}
	/**
	 * 按SQL语句获取记录结果，返回数组
	 *
	 * @param sql  执行的SQL语句
	 */
	public function getOne($sql)
	{
		if( ! $result = $this->exec($sql) )return array();
		if( ! mysqli_num_rows($result) )return array();
		$row = array();
		$row=mysqli_fetch_array($result,MYSQL_ASSOC);
		mysqli_free_result($result);
		return $row;
	}
	public function getField($field,$conditions)
	{
		$data=array();
		if(is_array($conditions)){
			$join = array();
			foreach( $conditions as $key => $condition ){
				$condition = $this->__val_escape($condition);
				$join[] = "{$key} = {$condition}";
			}
			$where = "WHERE ".join(" AND ",$join);
		}else{
			if(null != $conditions)$where = "WHERE ".$conditions;
		}
		if(is_array($field)){
			$fields=implode(',', $field);
			$sql = "select {$fields} from  {$this->tbl_name} {$where} limit 1";
			$data=$this->getOne($sql);
			return $data;
		}
		$sql = "select {$field} from  {$this->tbl_name} {$where} limit 1";
		$data=$this->getOne($sql);
		$return=isset($data[$field])?$data[$field]:'';
		return $return;
	}
	/**
	 * key=>val形式的一维数据
	 */
	public function getArray($sql,$showType=0)
	{
		if( ! $result = $this->exec($sql) )return array();
		if( ! mysqli_num_rows($result) )return array();
		$rows = array();
		while($row = mysqli_fetch_array($result,MYSQL_NUM)){
			if($showType==0){
				$rows[$row[0]]=$row[0]." ".$row[1];
			}else{
				$rows[$row[0]]=$row[1];
			}
		}
		mysqli_free_result($result);
		return $rows;
	}
	/**
	 * 在数据表中新增一行数据
	 *
	 * @param row 数组形式，数组的键是数据表中的字段名，键对应的值是需要新增的数据。
	 */
	public function create($row)
	{
		if(!is_array($row))return FALSE;
		$row = $this->__prepera_format($row);
		if(empty($row))return FALSE;
		foreach($row as $key => $value){
			$cols[] = $key;
			$vals[] = $this->__val_escape($value);
		}
		$col = join(',', $cols);
		$val = join(',', $vals);
		$sql = "INSERT INTO {$this->tbl_name} ({$col}) VALUES ({$val})";
		if( FALSE != $this->exec($sql) ){ // 获取当前新增的ID
			$newinserid = $this->newinsertid();
			if($newinserid){
				return $newinserid;
			}else{
				return true;
			}
		}
		return FALSE;
	}
	/**
	 * 在数据表中新增多条记录
	 *
	 * @param rows 数组形式，每项均为create的$row的一个数组
	 */
	public function createAll($rows)
	{
		foreach($rows as $row){
			$newinserid=$this->create($row);
		}
		return $newinserid;
	}
	/**
	 * 修改表
	 */
	public function update($conditions, $row, $needQuotes=true)
	{
		$where = "";
		$row = $this->__prepera_format($row);
		if(empty($row))return FALSE;
		if(is_array($conditions)){
			$join = array();
			foreach( $conditions as $key => $condition ){
				$condition = $this->__val_escape($condition);
				$join[] = "{$key} = {$condition}";
			}
			$where = "WHERE ".join(" AND ",$join);
		}else{
			if(null != $conditions)$where = "WHERE ".$conditions;
		}
		foreach($row as $key => $value){
			if ($needQuotes)
			$value = $this->__val_escape($value);
			$vals[] = "{$key} = {$value}";
		}
		$values = join(", ",$vals);
		$sql = "UPDATE {$this->tbl_name} SET {$values} {$where}";
		return $this->exec($sql);
	}
	/**
	 * 返回当前插入记录的主键ID
	 */
	public function newinsertid()
	{
		return mysqli_insert_id($this->conn);
	}

	/**
	 * 格式化带limit的SQL语句
	 */
	public function setlimit($sql, $limit)
	{
		return $sql. " LIMIT {$limit}";
	}
	public function delete($conditions)
	{
		if(is_array($conditions)){
			$join = array();
			foreach( $conditions as $key => $condition ){
				$condition = $this->__val_escape($condition);
				$join[] = "{$key} = {$condition}";
			}
			$where = "WHERE ".join(" AND ",$join);
		}else{
			if(null != $conditions)$where = "WHERE ".$conditions;
		}
		$sql="delete from {$this->tbl_name} {$where}";
		if ($this->exec($sql)) {
			if ($this->affected_rows() > 0) {
				return true;
			}
		}
		return false;
	}
	/**
	 * 执行一个SQL语句
	 *
	 * @param sql 需要执行的SQL语句
	 */
	public function exec($sql)
	{
		$this->arrSql[] = $sql;
		if( $result = mysqli_query($this->conn,$sql) ){
			return $result;
		}else{
			return false;
		}
	}
	public function execAll($sql)
	{
		if( $result = mysqli_multi_query($this->conn,$sql) ){
			mysqli_free_result($result);
			return $result;
		}else{
			die("{$sql}<br />执行错误: " . iconv('gbk','utf-8',mysqli_error()));
		}
	}
	/**
	 * 返回影响行数
	 */
	public function affected_rows()
	{
		return mysqli_affected_rows($this->conn);
	}
	/**
	 * 查询结果集数目
	 */
	public function num_rows()
	{
		return mysqli_num_rows($this->conn);
	}

	/**
	 * 获取数据表结构
	 *
	 * @param tbl_name  表名称
	 */
	public function getTable($tbl_name)
	{
		return $this->getAll("DESCRIBE {$tbl_name}");
	}

	/**
	 * 对特殊字符进行过滤
	 *
	 * @param value  值
	 */
	public function __val_escape($value) {
		if(is_null($value))return 'NULL';
		if(is_bool($value))return $value ? 1 : 0;
		if(is_int($value))return (int)$value;
		if(is_float($value))return (float)$value;
		if(@get_magic_quotes_gpc()) $value = stripslashes($value);
		return '\''.mysqli_real_escape_string( $this->conn,$value).'\'';
	}
	/**
	 * 按表字段调整适合的字段
	 * @param rows    输入的表字段
	 */
	private function __prepera_format($rows)
	{
		$columns = $this->getTable($this->tbl_name);
		$newcol = array();
		foreach( $columns as $col ){
			$newcol[$col['Field']] = $col['Field'];
		}
		return array_intersect_key($rows,$newcol);
	}
	/**
	 *
	 * 是否存在
	 * @param $table
	 * @param $where
	 */
	public function exist($table,$conditions='')
	{
		if(is_array($conditions)){
			$join = array();
			foreach( $conditions as $key => $condition ){
				$condition = $this->__val_escape($condition);
				$join[] = "{$key} = {$condition}";
			}
			$where = join(" AND ",$join);
		}else{
			$where=$conditions;
		}
		$sql="select 1 as num from $table where 1=1 and $where limit 1";
		$res=$this->getOne($sql);
		if(!empty($res['num'])){
			return true;//存在
		}else{
			return false;//不存在
		}
	}

	/**
	 *
	 * 是否存在
	 * @param $table
	 * @param $where
	 */
	public function existReturn($table,$conditions='',$field='*')
	{
		if(is_array($conditions)){
			$join = array();
			foreach( $conditions as $key => $condition ){
				$condition = $this->__val_escape($condition);
				$join[] = "{$key} = {$condition}";
			}
			$where = join(" AND ",$join);
		}else{
			$where=$conditions;
		}
		$sql="select $field from $table where 1=1 and $where limit 1";
		$res=$this->getOne($sql);
		return $res;
	}


	/**
	 * 析构函数
	 */
	public function __destruct()
	{
        @mysqli_close($this->conn);
	}
}

