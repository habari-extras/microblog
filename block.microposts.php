<ul>
<?php foreach ( $content->posts as $post ) : ?>
	<li>
		<?php echo $post->content_out; ?> @ <a href="<?php echo $post->permalink; ?>"><?php $post->pubdate->out(); ?></a>
	</li>
	<?php endforeach; ?>
</ul>
<p><small><a href="<?php echo $content->feed; ?>"><?php echo _t( 'Feed', 'microblog' ); ?></a></small></p>