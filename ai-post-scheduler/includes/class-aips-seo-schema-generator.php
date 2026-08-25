<?php
/**
 * SEO Schema.org (JSON-LD) Generator
 *
 * Generates rich structured data arrays (Article, BlogPosting, FAQPage, HowTo,
 * BreadcrumbList) for posts. Supports automatic detection and parsing of
 * FAQ Q&A blocks and HowTo steps directly from post content.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Schema_Generator {

	/**
	 * Generate all enabled Schema.org JSON-LD objects for a post.
	 *
	 * @param WP_Post|int $post         Post object or ID.
	 * @param array       $schema_types Enabled schema types (e.g., ['article', 'faq', 'howto', 'breadcrumbs']).
	 * @param array       $seo_data     Canonical SEO metadata.
	 * @return array Multi-schema graph array or individual schema structures.
	 */
	public function generate_for_post($post, array $schema_types = array(), array $seo_data = array()) {
		$post_obj = is_numeric($post) ? get_post($post) : $post;
		if (!($post_obj instanceof WP_Post)) {
			return array();
		}

		if (empty($schema_types)) {
			$schema_types = array('article', 'breadcrumbs');
		}

		$graph = array();

		if (in_array('article', $schema_types, true) || in_array('blog_posting', $schema_types, true)) {
			$graph[] = $this->build_article_schema($post_obj, $seo_data);
		}

		if (in_array('breadcrumbs', $schema_types, true)) {
			$breadcrumbs = $this->build_breadcrumb_schema($post_obj);
			if (!empty($breadcrumbs)) {
				$graph[] = $breadcrumbs;
			}
		}

		if (in_array('faq', $schema_types, true)) {
			$faq_schema = $this->build_faq_schema($post_obj);
			if (!empty($faq_schema)) {
				$graph[] = $faq_schema;
			}
		}

		if (in_array('howto', $schema_types, true)) {
			$howto_schema = $this->build_howto_schema($post_obj);
			if (!empty($howto_schema)) {
				$graph[] = $howto_schema;
			}
		}

		return apply_filters('aips_seo_generated_schema', $graph, $post_obj, $schema_types, $seo_data);
	}

	/**
	 * Build standard Article / BlogPosting schema.
	 *
	 * @param WP_Post $post     Post object.
	 * @param array   $seo_data Canonical SEO metadata.
	 * @return array
	 */
	public function build_article_schema(WP_Post $post, array $seo_data = array()) {
		$permalink = get_permalink($post->ID);
		$title = !empty($seo_data['seo_title']) ? $seo_data['seo_title'] : $post->post_title;
		$description = !empty($seo_data['meta_description']) ? $seo_data['meta_description'] : wp_trim_words($post->post_content, 35, '');

		$author_data = array(
			'@type' => 'Person',
			'name'  => get_the_author_meta('display_name', $post->post_author),
			'url'   => get_author_posts_url($post->post_author),
		);

		$publisher_data = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo('name'),
			'url'   => home_url('/'),
		);

		$custom_logo_id = get_theme_mod('custom_logo');
		if ($custom_logo_id) {
			$logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
			if ($logo_url) {
				$publisher_data['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
			}
		}

		$schema = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'BlogPosting',
			'mainEntityOfPage'    => array(
				'@type' => 'WebPage',
				'@id'   => $permalink,
			),
			'headline'            => $title,
			'description'         => $description,
			'datePublished'       => get_the_date('c', $post),
			'dateModified'        => get_the_modified_date('c', $post),
			'author'              => $author_data,
			'publisher'           => $publisher_data,
		);

		if (has_post_thumbnail($post->ID)) {
			$thumb_url = get_the_post_thumbnail_url($post->ID, 'full');
			if ($thumb_url) {
				$schema['image'] = $thumb_url;
			}
		}

		if (!empty($seo_data['focus_keyword'])) {
			$schema['keywords'] = $seo_data['focus_keyword'];
		}

		return $schema;
	}

	/**
	 * Build BreadcrumbList schema based on categories and site root.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	public function build_breadcrumb_schema(WP_Post $post) {
		$items = array();
		$position = 1;

		// 1. Home
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => __('Home', 'ai-post-scheduler'),
			'item'     => home_url('/'),
		);

		// 2. Category (if any)
		$categories = get_the_category($post->ID);
		if (!empty($categories) && is_array($categories)) {
			$category = $categories[0];
			$cat_link = get_category_link($category->term_id);
			if ($cat_link) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => $category->name,
					'item'     => $cat_link,
				);
			}
		}

		// 3. Current Post
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $post->post_title,
			'item'     => get_permalink($post->ID),
		);

		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * Automatically extract and build FAQPage schema from post HTML.
	 *
	 * Looks for heading questions (h2/h3 containing '?' or inside FAQ blocks)
	 * paired with following paragraph answers.
	 *
	 * @param WP_Post $post Post object.
	 * @return array|null FAQ schema array or null if no FAQs found.
	 */
	public function build_faq_schema(WP_Post $post) {
		$content = $post->post_content;
		if (empty($content)) {
			return null;
		}

		$faqs = array();

		// Match <h3> or <h4> containing a question, followed by <p> tags
		if (preg_match_all('/<h[2-4][^>]*>(.*?\?)<\/h[2-4]>\s*<p>(.*?)<\/p>/is', $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$question = wp_strip_all_tags($match[1]);
				$answer   = wp_strip_all_tags($match[2]);

				if (!empty($question) && !empty($answer)) {
					$faqs[] = array(
						'@type'          => 'Question',
						'name'           => trim($question),
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => trim($answer),
						),
					);
				}
			}
		}

		if (empty($faqs)) {
			return null;
		}

		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $faqs,
		);
	}

	/**
	 * Automatically extract and build HowTo schema from post HTML.
	 *
	 * @param WP_Post $post Post object.
	 * @return array|null HowTo schema array or null if no steps found.
	 */
	public function build_howto_schema(WP_Post $post) {
		$content = $post->post_content;
		if (empty($content)) {
			return null;
		}

		$steps = array();

		// Match numbered steps in headings e.g. "<h3>Step 1: ...</h3><p>...</p>"
		if (preg_match_all('/<h[2-4][^>]*>(?:Step\s*\d+[:\.]?\s*)(.*?)<\/h[2-4]>\s*<p>(.*?)<\/p>/is', $content, $matches, PREG_SET_ORDER)) {
			$pos = 1;
			foreach ($matches as $match) {
				$step_title = wp_strip_all_tags($match[1]);
				$step_text  = wp_strip_all_tags($match[2]);

				if (!empty($step_title) && !empty($step_text)) {
					$steps[] = array(
						'@type'    => 'HowToStep',
						'position' => $pos++,
						'name'     => trim($step_title),
						'text'     => trim($step_text),
					);
				}
			}
		}

		if (empty($steps)) {
			return null;
		}

		return array(
			'@context' => 'https://schema.org',
			'@type'    => 'HowTo',
			'name'     => $post->post_title,
			'step'     => $steps,
		);
	}
}
