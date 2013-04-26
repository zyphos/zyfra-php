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
                231=>9, 199=>9, 57=>9,
                41=>'-',
                65=>'A', 97=>'A',
                66=>'B', 98=>'B',
                67=>'C', 99=>'C',
                68=>'D', 100=>'D',
                69=>'E', 101=>'E',
                70=>'F', 102=>'F',
                71=>'G', 103=>'G',
                72=>'H', 104=>'H',
                73=>'I', 105=>'I',
                74=>'J', 106=>'J',
                75=>'K', 107=>'K',
                76=>'L', 108=>'L',
                77=>'M', 109=>'M',
                78=>'N', 110=>'N',
                79=>'O', 111=>'O',
                80=>'P', 112=>'P',
                81=>'Q', 113=>'Q',
                82=>'R', 114=>'R',
                83=>'S', 115=>'S',
                84=>'T', 116=>'T',
                85=>'U', 117=>'U',
                86=>'V', 118=>'V',
                87=>'W', 119=>'W',
                88=>'X', 120=>'X',
                89=>'Y', 121=>'Y',
                90=>'Z', 122=>'Z',
                61=>'/'
        );
        $r = '';
        try{
            for ($i=0; $i< strlen($barcode);$i++){
                $char = $barcode[$i];
                $key = ord($char);
                if (!array_key_exists($key, $c)) {
                    throw new Exception($char.' ['.$key.'] not recognize as barcode');
                }
                $r .= $c[$key];
            }
        }catch (Exception $e) {
            $r = '';
        }
        return $r;
    }
}
?>