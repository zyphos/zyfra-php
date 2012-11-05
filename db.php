<?php
/*****************************************************************************
*
*		 Db Class
*		 ---------------
*
*		 Unification for Database
*
*    Copyright (C) 2009 De Smet Nicolas (<http://ndesmet.be>).
*    All Rights Reserved
*    
*
*    This program is free software: you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation, either version 3 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*****************************************************************************/

//require_once("../config/config.php");
switch($config->db_type){
  case "mysql":
    require_once("db_mysql.php");
    $MySQL->host = $config->db_server;
    $MySQL->user = $config->db_user;
    $MySQL->password = $config->db_password;
    $MySQL->default_db = $config->db_name;
    if(!isset($db) || !is_subclass_of($db, 'Cdb_common')) $db = $MySQL;
    break;
}
?>
