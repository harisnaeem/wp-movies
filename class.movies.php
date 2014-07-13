<?php

require "class.movie.php";

class Movies {
    
    public static $TMDB = false;
    private static $TMDB_API_KEY = false;

	public static function init() {
		if(Movies::is_configured()) {
		    self::connect_to_tmdb();
		}
	}

    public static function connect_to_tmdb() {
        if(self::$TMDB_API_KEY) {
		    require("tmdb.v3.php");
		    self::$TMDB = new TMDBv3(self::$TMDB_API_KEY);
		}
    }

    public static function TMDb( $tmdb_id ) {
        $data = self::data_from_tmdb_basic_search(
            array('id' => $tmdb_id),
            $get_detail=true
        );
        return new Movie($data);
    }

	public static function render_template( $template_name, $context ) {
	    $file_path = MOVIES__PLUGIN_DIR . '/templates/' . $template_name;
	    extract($context);
	    require($file_path);
	}

	public static function is_configured() {
	    self::$TMDB_API_KEY = get_option('zmovies_tmdb_key');
	    if(!self::$TMDB_API_KEY) {
	        return false;
	    }
	    return true;
	}
	
	public static function tmdb_image( $file_path, &$size='original', $force_copy=false ) {
	    if($size == 'poster') { $size = Movie::POSTER_WIDTH; }
	    if($size == 'backdrop') { $size = Movie::BACKDROP_WIDTH; }
        $wp_upload_dir = wp_upload_dir();
        $file_destination = '/tmdb/' . $size . $file_path;
        if( !file_exists($wp_upload_dir['path'] . $file_destination) || $force_copy ) {
            self::copy_tmdb_image( $file_path, $size ); 
        }
        $image_url = $wp_upload_dir['url'] . $file_destination;
        return $image_url;
	}
	
	public static function copy_tmdb_image( $file_path, &$size='original' ) {
	    if($size == 'poster') { $size = Movie::POSTER_WIDTH; }
	    if($size == 'backdrop') { $size = Movie::BACKDROP_WIDTH; }
	    $image_url = self::$TMDB->getImageURL($size) . $file_path;
	    $image_to_upload = file_get_contents( $image_url );
        $wp_upload_dir = wp_upload_dir();
        $tmdb_upload_dir = $wp_upload_dir['path'] . '/tmdb';
        if( !file_exists( $tmdb_upload_dir ) ) {
            mkdir( $tmdb_upload_dir );
        }
        $size_upload_dir = $tmdb_upload_dir . '/' . $size;
        if( !file_exists( $size_upload_dir ) ) {
            mkdir( $size_upload_dir );
        }
        $image_path = $size_upload_dir . $file_path;
        $fh = fopen( $image_path, 'w' );
        fwrite( $fh, $image_to_upload );
        fclose( $fh );
	}
	
	public static function data_from_tmdb_basic_search( $result, $get_detail=false ) {
	    if($get_detail) {
	        $result = self::$TMDB->movieDetail($result['id']);
	    }
	    if($result['release_date']) {
	        if(strlen($result['release_date']) < 4) {
	            $release_year = false;
	        } else {
	            $release_year = substr($result['release_date'], 0, 4);
	        }
	    }
	    $data = array(
            'tmdb_id' => $result['id'],
            'year' => $release_year,
            'backdrop_path' => $result['backdrop_path'],
            'poster_path' => $result['poster_path'],
            'title' => $result['title'],
            'genres' => array(),
            'imdb_id' => false,
            'runtime' => false,
            'languages' => array(),
            'overview' => false
        );
	    if($get_detail) {
	        if(isset($result['genres'])) {
                foreach($result['genres'] as $genre) {
                    $data['genres'][] = $genre['name'];
                }
            }
            if(isset($result['spoken_languages'])) {
                foreach($result['spoken_languages'] as $language) {
                    $data['languages'][] = $language['name'];
                }
            }
	        $data['imdb_id'] = $result['imdb_id'];
	        $data['runtime'] = $result['runtime'];
	        $data['overview'] = $result['overview'];
	    }
	    return $data;
	}
	
	public static function plugin_activation() {
	    self::restore_defaults();
	}
	
	public static function plugin_deactivation() {
	    self::restore_defaults();
	}
	
	public static function restore_defaults() {
	    foreach(self::$settings as $opt_name => $opt) {
	        update_option( $opt_name, $opt['default'] );
	    }
	    self::clear_all_data();
	}
	
	public static function clear_all_data() {
	    $posts = self::posts_with_movie_data();
	    foreach($posts as $post) {
	        delete_post_meta( $post->ID, 'tmdb_id' );
	        delete_post_meta( $post->ID, '_zmovies_json' );
	    }
	}
	
	public static function posts_with_movie_data() {
	    $args = array(
	        'posts_per_page' => 9999,
            'meta_key'         => 'tmdb_id',
            'post_type'        => get_option('zmovies_post_type'),
            'suppress_filters' => true
        );
        return get_posts( $args );
	}

    public static $settings = array(
        'zmovies_tmdb_key' => array(
            'default' => '',
            'label' => 'TMDB API Key',
            'description' => 'Your TMDb API key'
        ),
        'zmovies_poster_width' => array(
            'default' => 'w300',
            'label' => 'Default poster width',
            'description' => 'e.g. `original` or `w300`. Must be supported by TMDb.'
        ),
        'zmovies_backdrop_width' => array(
            'default' => 'original',
            'label' => 'Default backdrop width',
            'description' => 'e.g. `original` or `w300`. Must be supported by TMDb.'
        ),
        'zmovies_post_type' => array(
            'default' => 'tribe_events',
            'label' => 'Post type (optional)',
            'description' => '`tribe_events` for use with Events Calendar (Pro); set to blank or other post type as needed'
        )
    );

}

?>