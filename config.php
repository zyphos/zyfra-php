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

class zyfra_config{
  //php
  var $php_ext = "php";
  
  var $siteId = 2; //to avoid problem during merges
  
  
  /***************************
  * Other options
  ****************************/
  //Theme
  var $theme_name = "normal";
  
  //Database
  var $db_type = "mysql";
  var $db_server = "localhost";
  var $db_user = "user";
  var $db_password = "password";
  var $db_name = "my_db";

  var $db_table_prefix = "my_";
}
if(!isset($config)){
  $config = new zyfra_config;
}
?>
