<?php
/**
 * Product Q&A
 *
 * @package MobiCare_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MobiCare_Questions
 */
class MobiCare_Questions {

	const CPT = 'mc_question';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'wp_ajax_mobicare_ask_question', array( __CLASS__, 'ajax_ask' ) );
		add_action( 'wp_ajax_nopriv_mobicare_ask_question', array( __CLASS__, 'ajax_ask' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_meta' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * Register CPT.
	 */
	public static function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( 'پرسش‌های محصول', 'mobicare-core' ),
					'singular_name' => __( 'پرسش', 'mobicare-core' ),
					'add_new_item'  => __( 'افزودن پرسش', 'mobicare-core' ),
					'edit_item'     => __( 'پاسخ / ویرایش پرسش', 'mobicare-core' ),
					'menu_name'     => __( 'پرسش‌ها', 'mobicare-core' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=product',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'author' ),
				'menu_icon'           => 'dashicons-editor-help',
			)
		);
	}

	/**
	 * Meta boxes.
	 */
	public static function meta_boxes() {
		add_meta_box(
			'mc_question_meta',
			__( 'جزئیات پرسش', 'mobicare-core' ),
			array( __CLASS__, 'render_meta_box' ),
			self::CPT,
			'side',
			'high'
		);
	}

	/**
	 * Meta box HTML.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'mc_question_meta', 'mc_question_meta_nonce' );
		$product_id = (int) get_post_meta( $post->ID, '_product_id', true );
		$answer     = get_post_meta( $post->ID, '_answer', true );
		$status     = get_post_meta( $post->ID, '_q_status', true ) ?: 'pending';
		?>
		<p>
			<label for="mc_product_id"><strong><?php esc_html_e( 'شناسه محصول', 'mobicare-core' ); ?></strong></label>
			<input type="number" name="mc_product_id" id="mc_product_id" value="<?php echo esc_attr( $product_id ); ?>" class="widefat" min="1">
			<?php if ( $product_id ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><?php echo esc_html( get_the_title( $product_id ) ); ?></a>
			<?php endif; ?>
		</p>
		<p>
			<label for="mc_q_status"><strong><?php esc_html_e( 'وضعیت', 'mobicare-core' ); ?></strong></label>
			<select name="mc_q_status" id="mc_q_status" class="widefat">
				<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'در انتظار', 'mobicare-core' ); ?></option>
				<option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'تأیید شده', 'mobicare-core' ); ?></option>
				<option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'رد شده', 'mobicare-core' ); ?></option>
			</select>
		</p>
		<p>
			<label for="mc_answer"><strong><?php esc_html_e( 'پاسخ ادمین', 'mobicare-core' ); ?></strong></label>
			<textarea name="mc_answer" id="mc_answer" class="widefat" rows="6"><?php echo esc_textarea( $answer ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['mc_question_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mc_question_meta_nonce'] ) ), 'mc_question_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['mc_product_id'] ) ) {
			update_post_meta( $post_id, '_product_id', absint( $_POST['mc_product_id'] ) );
		}
		if ( isset( $_POST['mc_q_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['mc_q_status'] ) );
			if ( in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
				update_post_meta( $post_id, '_q_status', $status );
			}
		}
		if ( isset( $_POST['mc_answer'] ) ) {
			update_post_meta( $post_id, '_answer', sanitize_textarea_field( wp_unslash( $_POST['mc_answer'] ) ) );
		}
	}

	/**
	 * Columns.
	 *
	 * @param array $cols Cols.
	 * @return array
	 */
	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['mc_product'] = __( 'محصول', 'mobicare-core' );
				$new['mc_status']  = __( 'وضعیت', 'mobicare-core' );
			}
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $col Col.
	 * @param int    $id  ID.
	 */
	public static function column_content( $col, $id ) {
		if ( 'mc_product' === $col ) {
			$pid = (int) get_post_meta( $id, '_product_id', true );
			echo $pid ? esc_html( get_the_title( $pid ) ) : '—';
		}
		if ( 'mc_status' === $col ) {
			$status = get_post_meta( $id, '_q_status', true ) ?: 'pending';
			$labels = array(
				'pending'  => __( 'در انتظار', 'mobicare-core' ),
				'approved' => __( 'تأیید', 'mobicare-core' ),
				'rejected' => __( 'رد', 'mobicare-core' ),
			);
			echo esc_html( $labels[ $status ] ?? $status );
		}
	}

	/**
	 * Get approved questions for product.
	 *
	 * @param int $product_id Product ID.
	 * @return WP_Post[]
	 */
	public static function get_for_product( $product_id ) {
		$q = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_product_id',
					'value' => absint( $product_id ),
				),
				array(
					'key'   => '_q_status',
					'value' => 'approved',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		return $q->posts;
	}

	/**
	 * AJAX ask.
	 */
	public static function ajax_ask() {
		check_ajax_referer( 'mobicare_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$question   = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'محصول نامعتبر است.', 'mobicare-core' ) ), 400 );
		}
		if ( strlen( $question ) < 5 ) {
			wp_send_json_error( array( 'message' => __( 'متن پرسش خیلی کوتاه است.', 'mobicare-core' ) ), 400 );
		}

		// Rate limit simple.
		$ip_key = 'mc_q_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		if ( get_transient( $ip_key ) ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً کمی صبر کنید و دوباره تلاش کنید.', 'mobicare-core' ) ), 429 );
		}
		set_transient( $ip_key, 1, 60 );

		$author_id = get_current_user_id();
		$title     = wp_trim_words( $question, 12, '…' );

		$post_id = wp_insert_post( array(
			'post_type'    => self::CPT,
			'post_title'   => $title,
			'post_content' => $question,
			'post_status'  => 'publish',
			'post_author'  => $author_id ? $author_id : 0,
		), true );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'ثبت پرسش ناموفق بود.', 'mobicare-core' ) ), 500 );
		}

		update_post_meta( $post_id, '_product_id', $product_id );
		update_post_meta( $post_id, '_q_status', 'pending' );
		if ( $name ) {
			update_post_meta( $post_id, '_asker_name', $name );
		}
		if ( $email ) {
			update_post_meta( $post_id, '_asker_email', $email );
		}

		// Notify admin.
		$admin_email = get_option( 'admin_email' );
		if ( $admin_email ) {
			wp_mail(
				$admin_email,
				sprintf( '[%s] پرسش جدید محصول', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
				sprintf( "محصول: %s\nپرسش: %s\n\nمدیریت: %s", get_the_title( $product_id ), $question, admin_url( 'post.php?post=' . $post_id . '&action=edit' ) )
			);
		}

		wp_send_json_success( array(
			'message' => __( 'پرسش شما ثبت شد و پس از بررسی نمایش داده می‌شود.', 'mobicare-core' ),
		) );
	}

	/**
	 * Render questions UI for product.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function render( $product_id = 0 ) {
		$product_id = $product_id ? $product_id : get_the_ID();
		$items      = self::get_for_product( $product_id );
		?>
		<div class="mc-questions" data-product-id="<?php echo esc_attr( $product_id ); ?>">
			<?php if ( empty( $items ) ) : ?>
				<p class="mc-muted"><?php esc_html_e( 'هنوز پرسشی ثبت نشده است. اولین نفر باشید.', 'mobicare-core' ); ?></p>
			<?php else : ?>
				<?php foreach ( $items as $item ) :
					$answer = get_post_meta( $item->ID, '_answer', true );
					$aname  = get_post_meta( $item->ID, '_asker_name', true );
					if ( ! $aname && $item->post_author ) {
						$u = get_user_by( 'id', $item->post_author );
						$aname = $u ? $u->display_name : '';
					}
					?>
					<div class="mc-question">
						<div class="mc-question__meta">
							<?php echo esc_html( $aname ? $aname : __( 'کاربر', 'mobicare-core' ) ); ?>
							·
							<time datetime="<?php echo esc_attr( get_the_date( 'c', $item ) ); ?>"><?php echo esc_html( get_the_date( '', $item ) ); ?></time>
						</div>
						<div class="mc-question__text"><?php echo esc_html( $item->post_content ); ?></div>
						<?php if ( $answer ) : ?>
							<div class="mc-question__answer">
								<strong><?php esc_html_e( 'پاسخ فروشگاه:', 'mobicare-core' ); ?></strong>
								<?php echo esc_html( $answer ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<form class="mc-question-form" id="mc-question-form">
				<h4><?php esc_html_e( 'پرسش خود را بپرسید', 'mobicare-core' ); ?></h4>
				<?php if ( ! is_user_logged_in() ) : ?>
					<input type="text" name="name" class="mc-input" placeholder="<?php esc_attr_e( 'نام', 'mobicare-core' ); ?>" required>
					<input type="email" name="email" class="mc-input" placeholder="<?php esc_attr_e( 'ایمیل', 'mobicare-core' ); ?>" required>
				<?php endif; ?>
				<textarea name="question" required placeholder="<?php esc_attr_e( 'پرسش شما درباره این محصول...', 'mobicare-core' ); ?>"></textarea>
				<button type="submit" class="mc-btn mc-btn--primary"><?php esc_html_e( 'ارسال پرسش', 'mobicare-core' ); ?></button>
				<p class="mc-question-form__msg mc-muted" hidden></p>
			</form>
		</div>
		<script>
		(function(){
			var form=document.getElementById('mc-question-form');
			if(!form||!window.mobicareData)return;
			form.addEventListener('submit',function(e){
				e.preventDefault();
				var fd=new FormData(form);
				fd.append('action','mobicare_ask_question');
				fd.append('nonce',mobicareData.nonce);
				fd.append('product_id',form.closest('.mc-questions').getAttribute('data-product-id'));
				var msg=form.querySelector('.mc-question-form__msg');
				var btn=form.querySelector('button[type=submit]');
				btn.disabled=true;
				fetch(mobicareData.ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'})
					.then(function(r){return r.json();})
					.then(function(res){
						btn.disabled=false;
						msg.hidden=false;
						msg.textContent=(res.data&&res.data.message)||(res.success?'OK':'Error');
						if(res.success){form.reset();}
					})
					.catch(function(){btn.disabled=false;msg.hidden=false;msg.textContent='خطا';});
			});
		})();
		</script>
		<?php
	}
}
