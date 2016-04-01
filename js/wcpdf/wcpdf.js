(function($){

	"use strict";

	var file_frame;
		$(document).on('click', 'a.wcpdf-remove-image', function(e) {

			e.preventDefault();

			var $fieldID 					= $(this).parent().parent().children('.wcpdf_image_id' );
			var $fieldURL 				= $(this).parent().parent().children( '.wcpdf_image_url' );
			var $fieldIntChange 				= $(this).parent().parent().children( '.wcpdf_image_intern_change' );



			$fieldURL.attr('value','');
			$fieldURL.change();
			$fieldID.attr('value','');
			$fieldID.change();
			$fieldIntChange.attr('value','CHANGED');
			$fieldIntChange.change();
		});

		$(document).on('click', 'a.wcpdf-uppload-image', function(e) {

			e.preventDefault();

			var $fieldID 					= $(this).parent().children('.wcpdf_image_id' );
			var $fieldURL 				= $(this).parent().children( '.wcpdf_image_url' );
			var $fieldIntChange 				= $(this).parent().children( '.wcpdf_image_intern_change' );
			var $preViewWrapper 	= $(this).parent().children('.preview-image-wrapper');
			var $savedImage 		  = $(this).parent().children('.preview-image-wrapper').find('.iumb_saved_image');
			if (file_frame) file_frame.close();

			console.log($fieldIntChange);

			file_frame = wp.media.frames.file_frame = wp.media({
				title: $(this).data('uploader-title'),
				library: {
					type: 'image'
				},
				button: {
				text: $(this).data('uploader-button-text'),
				},
				multiple: false
			});

			file_frame.on('select', function() {
				var listIndex = $('#image-uploader-meta-box-list li').index($('#image-uploader-meta-box-list li:last')),
					selection = file_frame.state().get('selection');

				selection.map(function(attachment) {

					var attachment = attachment.toJSON();
					var imageURL = attachment.url;
					var imageID  = attachment.id;

					$('.iumb').val(attachment.url);

					if( attachment.url != '' ){

						$savedImage.remove();

						var preview = '<img src="'+imageURL+'" />'+
													'<a href="#" class="remove_image wcpdf-remove-image"><em>Remove</em></a>';

						$($preViewWrapper).html(preview);
						$fieldURL.attr('value',imageURL);
						$fieldURL.change();
						$fieldID.attr('value',imageID);
						$fieldID.change();
						$fieldIntChange.attr('value',imageID);
						$fieldIntChange.change();

					}

				});

			});

			file_frame.open();

		});

})(jQuery);
