/**
 * 青崖主题（Qingya）本地头像：用户资料页媒体库选择/移除。
 */
(function ($) {
	'use strict';

	var frame;

	$(function () {
		var $url = $('#qingya-avatar-url'),
			$preview = $('#qingya-avatar-preview'),
			$remove = $('#qingya-avatar-remove');

		$('#qingya-avatar-upload').on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: '选择头像',
				button: { text: '使用此图片' },
				multiple: false,
				library: { type: 'image' }
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var imgUrl = attachment.sizes && attachment.sizes.medium
					? attachment.sizes.medium.url
					: attachment.url;
				$url.val(imgUrl);
				$preview.find('img').attr('src', imgUrl);
				$remove.prop('disabled', false);
			});

			frame.open();
		});

		$remove.on('click', function () {
			$url.val('');
			$preview.find('img').attr('src', qingyaAvatar.default);
			$(this).prop('disabled', true);
		});
	});
})(jQuery);
