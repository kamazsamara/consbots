<?php

////////////////////////////////////////////////////////////////////////////////////////
//                                   Парсер шаблонов                                  //
////////////////////////////////////////////////////////////////////////////////////////



class parse_output
{
    function parse($act,$data)
    {
        switch($act)
        {
            case 'ping':
            
                
                //$array = files::get_raw_file('tpl/ping.tpl');
                    foreach($data as $key=>$val)
                    {
                    $file = file_get_contents('tpl/ping.tpl');
                        foreach($val as $k=>$v)
                        {
                            
                            $file = str_replace( "{".$k."}", $v, $file);
                            
                        }
                        $out = $out."\n".$file."\n";
                    }  
                    return $out;
            break;
            case 'Состояние системы':
                    foreach($data as $key=>$val)
                    {
                    $file = file_get_contents('tpl/status.tpl');
                        foreach($val as $k=>$v)
                        {
                            
                            $file = str_replace( "{".$k."}", $v, $file);
                            
                        }
                        $out = $out."\n".$file."\n";
                    }  
                    return $out;
            break;
            case 'Активные пользователи':
                    foreach($data as $key=>$val)
                    {
                    $file = file_get_contents('tpl/users.tpl');
                        foreach($val as $k=>$v)
                        {
                            
                            $file = str_replace( "{".$k."}", $v, $file);
                            
                        }
                        $out = $out."\n".$file."\n";
                    }  
                    return $out;            
            break;
            case 'Статус интерфейсов':

                    foreach($data as $key=>$val)
                    {
                    $file = file_get_contents('tpl/interfaces.tpl');
                        foreach($val as $k=>$v)
                        {
                            
                            $file = str_replace( "{".$k."}", $val[$k], $file);
                           
                        }
                        file_put_contents('logs/error.log', $out);
                        $out = $out."\n".$file."\n";
                    }  
                                  file_put_contents('logs/error.log', $out);
                    return $out;            
            break;
            case 'Активные РРР пользователи':
                    foreach($data as $key=>$val)
                    {
                    $file = file_get_contents('tpl/ppp_users.tpl');
                        foreach($val as $k=>$v)
                        {
                            
                            $file = str_replace( "{".$k."}", $v, $file);
                            
                        }
                        $out = $out."\n".$file."\n";
                    }  
                    return $out;            
            break;
            case 'Беспроводные клиенты':
                    foreach($data as $key=>$val)
                    {
                    $file = file_get_contents('tpl/wlan.tpl');
                        foreach($val as $k=>$v)
                        {
                            
                            $file = str_replace( "{".$k."}", $v, $file);
                            
                        }
                        $out = $out."\n".$file."\n";
                    }  
                    return $out;            
            break;
            case 'Выданые адреса':
                    foreach($data as $key=>$val)
                    {
                    $file = file_get_contents('tpl/addr.tpl');
                        foreach($val as $k=>$v)
                        {
                            
                            $file = str_replace( "{".$k."}", $v, $file);
                            
                        }
                        $out = $out."\n".$file."\n";
                    }  
                    return $out;            
            break;            
        }
    }

}

?>



              


              

              
               

              

              