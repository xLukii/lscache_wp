<?php defined( 'WPINC' ) || exit ; ?>

	<!-- build_setting_purge_on_upgrade -->
	<tr>
		<th>
			<?php $id = LiteSpeed_Config::O_PURGE_ON_UPGRADE ; ?>
			<?php $this->title( $id ) ; ?>
		</th>
		<td>
			<?php $this->build_switch( $id ) ; ?>
			<div class="litespeed-desc">
				<?php echo __( 'When enabled, the cache will automatically purge when any plugins, themes, or WordPress core is upgraded.', 'litespeed-cache' ) ; ?>
			</div>
		</td>
	</tr>