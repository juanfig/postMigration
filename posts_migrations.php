<?php
// Calico DB Config
$usuCalico = 'pantheon';
$passCalico = 'pantheon';
$hostCalico = '127.0.0.1';
$portCalico = '3302';
$DbCalico  = 'pantheon';

// Tigera DB Config
$usuTigera = 'pantheon';
$passTigera = 'pantheon';
$hostTigera = '127.0.0.1';
$portTigera = '3301';
$DbTigera  = 'pantheon';

function countRecords($conn, $sql){
    if ($result = selectRecords($conn, $sql)) {
        return mysqli_num_rows($result)>0 ? true : false;
    }
}

function selectRecords($conn, $sql){
    return mysqli_query($conn, $sql);
}

function singleRecord($conn, $sql){
    return mysqli_fetch_object(selectRecords($conn, $sql));
}

function addRecord($fields, $values, $table, $cnn){
    $sql = "INSERT INTO ".$table." (";
    foreach ($fields as $field) { $sql.=$field.", ";}
    $sql = substr($sql, 0, -2).") VALUES (";
    foreach ($values as $value) { $sql.="'".$value."', ";}
    $sql = substr($sql, 0, -2).")";
    $res = mysqli_query($cnn, $sql);
    return ($res) ? true : false;
}

function sanitize($conn,$var){
    return mysqli_real_escape_string($conn, $var);
}
try {
    $cnnTigera = mysqli_connect($hostTigera, $usuTigera, $passTigera, $DbTigera, $portTigera);
    $cnnCalico = mysqli_connect($hostCalico, $usuCalico, $passCalico, $DbCalico, $portCalico);

    $sql = 'select u.ID as user_id,u.*,p.* from wp_posts p ';
    $sql.= 'left join wp_users u on p.post_author = u.ID ';
    $sql.= 'where p.post_type="post" ';
    //$sql.= 'and p.post_name="a-2020-review-of-the-worlds-most-popular-kubernetes-cni" ';
    //$sql.= 'and p.post_name="calico-networking-for-kubernetes" ';
    //$sql.= ' and p.post_name="hands-on-with-calicos-ebpf-service-handling" ';

    $posts = selectRecords($cnnCalico, $sql);

    $countImg = 0;
    $fileToPathImg = fopen("img_path.txt", "w") or die("Unable to open file!");
    while ($post=mysqli_fetch_object($posts)){
        echo '[post-ID]='.$post->ID.", Permalink--> ".$post->post_name."\n";

        // verified if exist in tigera
        $existPostInTigera = countRecords($cnnTigera,'SELECT * FROM wp_posts WHERE post_name="'.$post->post_name.'"');
        //echo "\t Exist in Tigera DB : ".($existPostInTigera===0 ? "NO" : "YES")."\n";
        if (!$existPostInTigera) {

            // work with authors


            //work with post_content
            $content = $post->post_content;
            $fileContentTmp =  'context_tmp.txt';
            $imgString = '<img src=';

            file_put_contents($fileContentTmp, $content);
            $txt = fopen($fileContentTmp,'r');
            while ($line = fgets($txt)) {

                $isImg = strpos($line, $imgString);
                if ($isImg) {
                    $line1 = explode("</",$line);
                    $line2 = explode(">",$line1[0]);
                    $line3 = explode('"', $line2[1]);
                    $urlImg = $line3[1];
                    echo "\t".$urlImg."\n";
                    $countImg++;

                    fwrite($fileToPathImg, $urlImg."\n");

                }
            }
            unlink($fileContentTmp);
            echo "\tData Author : Calico-ID->".$post->user_id.", ".$post->display_name." (".$post->user_email.") ";
            $existAuthorInTigera = countRecords($cnnTigera, 'SELECT * FROM wp_users WHERE user_email="'.$post->user_email.'"');
            if (!$existAuthorInTigera) {
                $fields = [
                    'user_login',
                    'user_pass',
                    'user_nicename',
                    'user_email',
                    'user_url',
                    'user_registered',
                    'user_activation_key',
                    'user_status',
                    'display_name'
                ];
                $values = [
                     $post->user_login,
                     $post->user_pass,
                     $post->user_nicename,
                     $post->user_email,
                     $post->user_url,
                     $post->user_registered,
                     $post->user_activation_key,
                     $post->user_status,
                     $post->display_name
                ];
                // ADD USER in TIGERA
                //addRecord($fields,$values,'wp_users',$cnnTigera);
                echo ", Added in TIGERA with ID->";
                $authorInTigera = singleRecord($cnnTigera, 'SELECT * FROM wp_users WHERE user_email="'.$post->user_email.'"');
                echo $authorInTigera->ID."\n";

                // ADD METASUSER in TIGERA
                /*$countUserMetas = countRecords($cnnCalico, 'SELECT * FROM wp_usermeta WHERE user_id='.$post->user_id);
                //echo "\t Have PostMeta ?: ".($countUserMetas > 0 ? "YES" : "NO")."\n";
                if ($countUserMetas) {
                    $userMetas = selectRecords($cnnCalico, 'SELECT * FROM wp_usermeta WHERE user_id='.$post->user_id);
                    $fields = ['user_id', 'meta_key', 'meta_value'];
                    while ($userMeta=mysqli_fetch_object($userMetas))
                    {
                        $values = [$userMeta->user_id, $userMeta->meta_key, $userMeta->meta_value];
                        if (addRecord($fields,$values,'wp_usermeta',$cnnTigera)) {
                            //echo "\t\tAdd user meta  ".$userMeta->meta_key.": ".$userMeta->meta_value." \n";
                        }
                    }
                }*/
            } else {
                $authorInTigera = singleRecord($cnnTigera, 'SELECT * FROM wp_users WHERE user_email="'.$post->user_email.'"');
                echo 'Existente, ID-Tigera : '.$authorInTigera->ID."\n";
            }

            // work with posts
            $fields = [
                'post_author',
                'post_date',
                'post_date_gmt',
                'post_content',
                'post_title',
                'post_excerpt',
                'post_status',
                'comment_status',
                'ping_status',
                'post_password',
                'post_name',
                'to_ping',
                'pinged',
                'post_modified',
                'post_modified_gmt',
                'post_content_filtered',
                'post_parent',
                'guid',
                'menu_order',
                'post_type',
                'post_mime_type',
                'comment_count'
            ];
            $values = [
                $authorInTigera->ID,
                $post->post_date,
                $post->post_date_gmt,
                sanitize($cnnTigera, $post->post_content),
                $post->post_title,
                $post->post_excerpt,
                $post->post_status,
                $post->comment_status,
                $post->ping_status,
                $post->post_password,
                $post->post_name,
                $post->to_ping,
                $post->pinged,
                $post->post_modified,
                $post->post_modified_gmt,
                $post->post_content_filtered,
                $post->post_parent,
                str_replace('dev-project-calico-2020', 'live-tigera-2019', $post->guid),
                $post->menu_order,
                $post->post_type,
                $post->post_mime_type,
                $post->comment_count
            ];
            /*if (addRecord($fields,$values,'wp_posts',$cnnTigera)) {
                echo "\tPOST ADDED \n";
                $postAdded = singleRecord($cnnTigera, 'SELECT * FROM wp_posts WHERE post_name="'.$post->post_name.'"');
            }*/

            // work with PostMeta
            $countPostsMetas = countRecords($cnnCalico, 'SELECT * FROM wp_postmeta WHERE post_id='.$post->ID);
            //echo "\tHave PostMeta ?: ".($countPostsMetas > 0 ? "YES" : "NO")."\n";
            $postMetas = selectRecords($cnnCalico, 'SELECT * FROM wp_postmeta WHERE post_id='.$post->ID);
            $fields = ['post_id', 'meta_key', 'meta_value'];
            while ($postMeta=mysqli_fetch_object($postMetas))
            {
                //$valuesPostMeta = [$postAdded->ID, $postMeta->meta_key, $postMeta->meta_value];
                //work with thumbnail image
                if ($postMeta->meta_key === '_thumbnail_id') {
                    $sql = 'SELECT u.ID as user_id, u.*, p.*  FROM wp_posts p ';
                    $sql.= 'LEFT JOIN wp_users u on p.post_author = u.ID WHERE p.ID='.$postMeta->meta_value;
                    $existThumbnailInCalico = countRecords($cnnCalico, $sql);
                    if ($existThumbnailInCalico ) {
                        $thumbnailInCalico = singleRecord($cnnCalico, $sql);
                        echo "\tThumbnail in Calico : ID->".$thumbnailInCalico->ID." Path->".$thumbnailInCalico->guid."\n";
                        //verified if thumbnail exist in Tigera
                        $sql1 = 'SELECT u.ID as user_id, u.*, p.*  FROM wp_posts p ';
                        $sql1.='LEFT JOIN wp_users u on p.post_author = u.ID WHERE post_name="'.$thumbnailInCalico->post_name.'" AND post_type="attachment"';
                        $existThumbnailInTigera = countRecords($cnnTigera, $sql1);
                        if ($existThumbnailInTigera) {
                            $thumbnailInTigera = singleRecord($cnnTigera, $sql1);
                            echo "\tThumbnail in Tigera : ID->".$thumbnailInTigera->ID." Path->".$thumbnailInTigera->guid."\n";
                            $valuesPostMeta[2] = $thumbnailInTigera->ID;
                        }
                        //addRecord($fields,$valuesPostMeta,'wp_postmeta',$cnnTigera);
                    }
                } else {
                     //addRecord($fields,$valuesPostMeta,'wp_postmeta',$cnnTigera);
                }
            }
        } else {
            echo "\t  THE POST WAS NOT ADDED\n";
        }
    }
    echo "Cantidad total de Imagenes : ".$countImg."\n";
    fclose($fileToPathImg);
    mysqli_close($cnnCalico);
    mysqli_close($cnnTigera);
} catch (PDOException $e) {
    print "Error!: " . $e->getMessage();
}

