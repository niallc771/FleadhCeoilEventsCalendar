<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/wordpress/wp-config.php');
include_once($_SERVER['DOCUMENT_ROOT'].'/wordpress/wp-load.php');
include_once($_SERVER['DOCUMENT_ROOT'].'/wordpress/wp-includes/wp-db.php');

// Checks if login details are correct.
// PARAM $user: string representing the username
// PARAM $pass: string representing the password
// RETURNS bool true if login suceeds, false otherwise
function doLogin($user, $pass)
{
	$username = htmlspecialchars($user, ENT_QUOTES);
	$userinfo = get_userdatabylogin($username);
	$login_ok = wp_check_password( $pass, $userinfo->user_pass, $userinfo->ID );
    
    if($login_ok) 
        return $userinfo;
	else return null;
}

function get_postsb() {
	    if (!headers_sent()) {
      header('HTTP/1.1 200 OK', true);
      header("Content-Type: application/json; charset=$charset", true);
    }
 $my_query = new WP_Query('post_type=tribe_events&posts_per_page=-1');
	while ( $my_query->have_posts() ){
 		$my_query->the_post();
 		echo prettify(json_encode(posts_result($my_query->post)));
 		
}
wp_reset_query();
  }
   function posts_result($posts) {
    global $wp_query;
    return array(
      'count' => count($posts),
      'count_total' => (int) $wp_query->found_posts,
      'pages' => $wp_query->max_num_pages,
      'posts' => $posts
    );
  }
function prettify($ugly) {
    $pretty = "";
    $indent = "";
    $last = '';
    $pos = 0;
    $level = 0;
    $string = false;
    while ($pos < strlen($ugly)) {
      $char = substr($ugly, $pos++, 1);
      if (!$string) {
        if ($char == '{' || $char == '[') {
          if ($char == '[' && substr($ugly, $pos, 1) == ']') {
            $pretty .= "[]";
            $pos++;
          } else if ($char == '{' && substr($ugly, $pos, 1) == '}') {
            $pretty .= "{}";
            $pos++;
          } else {
            $pretty .= "$char\n";
            $indent = str_repeat('  ', ++$level);
            $pretty .= "$indent";
          }
        } else if ($char == '}' || $char == ']') {
          $indent = str_repeat('  ', --$level);
          if ($last != '}' && $last != ']') {
            $pretty .= "\n$indent";
          } else if (substr($pretty, -2, 2) == '  ') {
            $pretty = substr($pretty, 0, -2);
          }
          $pretty .= $char;
          if (substr($ugly, $pos, 1) == ',') {
            $pretty .= ",";
            $last = ',';
            $pos++;
          }
          $pretty .= "\n$indent";
        } else if ($char == ':') {
          $pretty .= ": ";
        } else if ($char == ',') {
          $pretty .= ",\n$indent";
        } else if ($char == '"') {
          $pretty .= '"';
          $string = true;
        } else {
          $pretty .= $char;
        }
      } else {
        if ($last != '\\' && $char == '"') {
          $string = false;
        }
        $pretty .= $char;
      }
      $last = $char;
    }
    return $pretty;
  }
// Redirects to new webpage
// PARAM: $location : the location of the new webpage to redirect to.
function redirectTo($location)
{
    header("location: $location");
}


function login($user, $pass, $remember = true) {
	$creds = array();
	$creds['user_login'] = $user;
	$creds['user_password'] = $pass;
	$creds['remember'] = $remember;
	$user = wp_signon( $creds, false );
        
    return $user;
}

function register($username, $password, $email)
{
    $user_id = wp_create_user( $username, $password, $email );
    return $user_id;
}
?>

