<?
/*****************************************************************************
 *
*		 Barcode Class
*		 ---------------
*
*		 Class handle barcode input from barcode reader.
*
*    Copyright (C) 2013 De Smet Nicolas (<http://ndesmet.be>).
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

class zyfra_barcode{
    static function scanned2txt($barcode){
        $c = array(
                224=>0, 192=>0, 48=>0,
                38=>1, 49=>1,
                233=>2, 201=>2, 50=>2,
                34=>3, 51=>3,
                39=>4, 52=>4,
                40=>5, 53=>5,
                167=>6, 54=>6,
                232=>7, 200=>7, 55=>7,
                33=>8, 56=>8,
                231=>9, 199=>9, 57=>9
        );
        $r = '';
        try{
            for ($i=0; $i< strlen($barcode);$i++){
                $r .= $c[ord($barcode[$i])];
            }
        }catch (Exception $e) {
            $r = '';
        }
        return $r;
    }
}
?>