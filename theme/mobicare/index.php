<?php
/**
 * Main template fallback
 *
 * @package MobiCare
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="main" class="mc-main" role="main">
	<div class="mc-container">
		<?php if ( have_posts() ) : ?>
			<div class="mc-posts">
				<?php while ( have_posts() ) : the_post(); ?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'mc-post-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a href="<?php the_permalink(); ?>" class="mc-post-card__thumb">
								<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
							</a>
						<?php endif; ?>
						<div class="mc-post-card__body">
							<h2 class="mc-post-card__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							<div class="mc-post-card__meta">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
							</div>
							<div class="mc-post-card__excerpt"><?php the_excerpt(); ?></div>
							<a class="mc-btn mc-btn--ghost mc-btn--sm" href="<?php the_permalink(); ?>">
								<?php esc_html_e( 'ادامه مطلب', 'mobicare' ); ?>
							</a>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php the_posts_pagination( array(
				'mid_size'  => 2,
				'prev_text' => '‹ ' . __( 'قبلی', 'mobicare' ),
				'next_text' => __( 'بعدی', 'mobicare' ) . ' ›',
			) ); ?>
		<?php else : ?>
			<div class="mc-empty">
				<div class="mc-empty__icon" aria-hidden="true">📭</div>
				<h1 class="mc-empty__title"><?php esc_html_e( 'محتوایی یافت نشد', 'mobicare' ); ?></h1>
				<p class="mc-empty__text"><?php esc_html_e( 'متأسفانه چیزی برای نمایش وجود ندارد.', 'mobicare' ); ?></p>
				<a class="mc-btn mc-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php esc_html_e( 'بازگشت به خانه', 'mobicare' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
