<?php
require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
require_once('rpi-html-parser.php');

/*
Plugin Name: Relilab Cron Import
Plugin URI:
Description: Plugin zum importieren von Posts von relilab
Version: 1.0
Author: Daniel Reintanz
*/

class RelilabCronImport
{
    private string $source_url;
    private array $newPostTemplate;
    private object $post;
    private string $post_status;

    public function __construct()
    {
        add_shortcode('relilab_import_cron', array($this, 'init_import'));
        add_action('relilab_import_cron', array($this, 'init_import'));
        add_filter('the_author', array('RelilabCronImport', 'get_the_original_author'));
    }

    function init_import()
    {
        if (have_rows('quelle', 'option')) {
            while (have_rows('quelle', 'option')) {
                the_row();
                $this->source_url = get_sub_field('relilab_import_url');
                $category_map = $this->get_mapped_category_array(get_sub_field('relilab_import_category_mapping'));
                $default_cat = get_sub_field('relilab_import_default_category');
                $create_new_cats = get_sub_field('relilab_import_is_create_new_categories');
                $this->post_status = get_sub_field('relilab_import_post_status');


                $response = wp_remote_get($this->source_url . '/wp-json/wp/v2/posts?per_page='. get_field('relilab_import_post_per_page', 'option'));
                if (is_array($response) && !is_wp_error($response)) {
                    $posts = json_decode($response['body']);
                    foreach ($posts as $this->post) {
                        $this->createNewPostTemplate();
                        $this->createTags();
                        if (!empty($this->post->categories)) {
                            foreach ($this->post->categories as $categoryId) {
                                $this->addCategoryByMap($categoryId, $category_map);
                                if (!empty($default_cat)) {
                                    $this->addDefaultCategory($default_cat);
                                } elseif (!empty($create_new_cats)) {
                                    $this->createNewCategory($categoryId);
                                }
                            }
                        }
                        $this->createPost();
                    }
                }
            }
        }
    }

    private function get_mapped_category_array($string)
    {
        $map = [];
        $lines = explode("\n", $string);
        foreach ($lines as $line) {
            $pair = explode(':', $line);
            $map[] = array(
                'source' => trim($pair[0]),
                'target' => trim($pair[1])
            );
        }
        return $map;
    }

    private function createNewPostTemplate(): void
    {
        $this->newPostTemplate = array(
            'post_date' => $this->post->date,
            'post_date_gmt' => $this->post->date_gmt,
            'post_content' => (new rpiHTMLParser())->parse_html($this->post->content->rendered),
            'post_title' => $this->post->title->rendered,
            'post_excerpt' => $this->post->excerpt->rendered,
            'post_status' => $this->post_status,
            'post_type' => $this->post->type,
            'comment_status' => $this->post->comment_status,
            'ping_status' => $this->post->ping_status,
            'post_modified' => $this->post->modified,
            'post_modified_gmt' => $this->post->modified_gmt,
            'post_category' => array(),
            'tags_input' => array(),
            'meta_input' => (array)$this->post->meta,
        );
    }

    private function createTags(): void
    {
        if (!empty($this->post->tags)) {
            foreach ($this->post->tags as $tag) {
                $tagResponse = wp_remote_get($this->source_url . '/wp-json/wp/v2/tags/' . $tag);
                if (is_array($tagResponse) && !is_wp_error($tagResponse)) {
                    $tag = json_decode($tagResponse['body']);
                    array_push($this->newPostTemplate ['tags_input'], $tag->name);
                }
            }
        }
    }

    /**
     * @param int $categoryId
     * @param array $category_map
     */
    private function addCategoryByMap(int $categoryId, array $category_map): void
    {
        if (!empty($category_map)) {
            foreach ($category_map as $category_map_entity) {
                $targetCategory = get_category_by_slug($category_map_entity['target']);
                if ($targetCategory && !in_array($targetCategory->id, $this->newPostTemplate ['post_category'])) {
                    $categoryResponse = wp_remote_get($this->source_url . '/wp-json/wp/v2/categories/' . $categoryId);
                    if (is_array($categoryResponse) && !is_wp_error($categoryResponse)) {
                        $category = json_decode($categoryResponse['body']);
                        if ($category_map_entity['source'] == $category->slug)
                            array_push($this->newPostTemplate ['post_category'], $targetCategory->cat_ID);
                    }
                }
            }
        }
    }

    /**
     * @param int $default_cat
     */
    private function addDefaultCategory(int $default_cat): void
    {
        if (!is_wp_error(get_the_category_by_ID($default_cat)) && !in_array($default_cat, $this->newPostTemplate['post_category']))
            array_push($this->newPostTemplate['post_category'], $default_cat);
    }

    /**
     * @param int $categoryId
     */
    private function createNewCategory(int $categoryId): void
    {
        $categoryResponse = wp_remote_get($this->source_url . '/wp-json/wp/v2/categories/' . $categoryId);
        if (is_array($categoryResponse) && !is_wp_error($categoryResponse)) {
            $category = json_decode($categoryResponse['body']);
            if (empty(get_category_by_slug($category->slug))) {
                $newCategory = wp_create_category($category->slug, 0);
                // TODO: ADD parent field option?
                if (is_object($newCategory) && !in_array($newCategory->id, $this->newPostTemplate ['post_category']))
                    array_push($this->newPostTemplate ['post_category'], $newCategory->id);
            }
        }
    }

    private function createPost(): void
    {
        if (isset($this->post->id)) {
            $postQuery = array(
                'post_type' => 'post',
                'posts_per_page' => 1,
                'meta_key' => 'relilab_imported_post_id',
                'meta_value' => $this->post->id,
                'meta_compare' => '==',
            );
            if (empty(get_posts($postQuery))) {
                $insertPostResult = wp_insert_post($this->newPostTemplate);
                if (!is_wp_error($insertPostResult)) {
                    update_post_meta($insertPostResult, 'relilab_imported_post_id', $this->post->id);
                    update_post_meta($insertPostResult, 'relilab_import_author', $this->getImportedPostAuthorNames());
                    get_post_meta($insertPostResult, 'relilab_imported_post_id', true);
                } else
                    echo $insertPostResult->get_error_message();
            }
        }
    }

    private function getImportedPostAuthorNames(): string
    {
        $authorNames = '';
        if (!empty($this->post->author)) {
            foreach ($this->post->author as $author) {
                $authorResponse = wp_remote_get($this->source_url . '/wp-json/wp/v2/users/' . $author);
                if (is_array($authorResponse) && !is_wp_error($authorResponse)) {
                    $author = json_decode($authorResponse['body']);
                    $authorNames .= $author->name;
                }
            }
        }
        return $authorNames;
    }

    static function get_the_original_author($display_name)
    {
        global $post;
        $author = get_post_meta($post->ID, 'relilab_import_author', true);
        if ($author)
            $display_name = $author['name'];
        return $display_name;
    }

}

new RelilabCronImport();