<?php
/**
 * Site footer.
 *
 * @package papounan2027
 */
?>
</div><!-- #primary -->

<footer id="colophon" class="site-footer">
	<p>
		<?php
		printf(
			/* translators: 1: Year, 2: Site name. */
			esc_html__( '© %1$s %2$s', 'papounan2027' ),
			esc_html( gmdate( 'Y' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
		?>
	</p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
