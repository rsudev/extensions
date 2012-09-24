<?php
/**
 * Special page class for the User Merge and Delete extension
 * allows sysops to merge references from one user to another user.
 * It also supports deleting users following merge.
 *
 * @ingroup Extensions
 * @author Aran Dunkley (http://www.organicdesign.co.nz/nad)
 *
 */
class jQueryUpload extends SpecialPage {

	function __construct() {
		parent::__construct( 'jQueryUpload', 'upload' );
		$this->head();
	}

	function execute( $param ) {
		global $wgOut, $wgResourceModules, $wgExtensionAssetsPath;
		$this->setHeaders();

		$wgOut->addWikiText( "== Upload files ==\n" );

		$wgOut->addHtml( $this->form() );
		$wgOut->addHtml( $this->templates() );
		$wgOut->addHtml( $this->scripts() );

	}

	/**
	 * Return a file icon for the passed filename
	 */
	public static function icon( $file ) {
		global $IP;
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$icons = "$IP/skins/common/images/icons";
		$icon = "$icons/fileicon-$ext.png";
		return file_exists( $icon ) ? $icon : "$icons/fileicon.png";
	}

	/**
	 * Ajax handler encapsulate jQueryUpload server-side functionality
	 */
	public static function server( $arg0 = false, $arg1 = false ) {
		global $wgScript, $wgUploadDirectory, $wgJQUploadAction;
		if( $wgJQUploadAction ) $_REQUEST['action'] = $wgJQUploadAction;

		header( 'Pragma: no-cache' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );

		// If there are args, then this is a file or thumbnail request
		if( $arg0 ) {
			global $wgUser;

			// Only return the file if the user is logged in
			if( !$wgUser->isLoggedIn() ) return false;

			// Get the file or thumb location
			if( $arg0 == 'thumb' ) {
				
				
				$name = $arg1;
				$file = "$wgUploadDirectory/jquery_upload_thumb/$name";
				
				// If not thumb exists, return an icon
				if( !file_exists( $file ) ) {
					if( $icon = self::icon( $file ) ) $file = $icon;
				}
			}
			else {
				$name = $arg0;
				$file = "$wgUploadDirectory/jquery_upload_files/$name";
			}

			// Set the headers, output the file and bail
			header( "Content-Type: " . mime_content_type ( $file ) );
			header( "Content-Disposition: inline; filename=\"$name\"" );
			header( "Content-Transfer-Encoding: binary" );
			header( "Pragma: private" );
			readfile( $file );
			return '';
		}

		header( 'Content-Disposition: inline; filename="files.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: OPTIONS, HEAD, GET, POST, PUT, DELETE' );
		header( 'Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size' );

		// Process the rename text inputs adde to the upload form rows
		if( array_key_exists( 'upload_rename_from', $_REQUEST ) && array_key_exists( 'files', $_FILES ) ) {
			foreach( $_REQUEST['upload_rename_from'] as $i => $from ) {
				if( false !== $j = array_search( $from, $_FILES['files']['name'] ) ) {
					$ext = pathinfo( $from, PATHINFO_EXTENSION );
					$_FILES['files']['name'][$j] = $_REQUEST['upload_rename_to'][$i] . ".$ext"; 
				}
			}
		}

		// So that meaningful errors can be sent back to the client
		error_reporting( E_ALL | E_STRICT );

		// Set the initial options for the upload file object
		$url = "$wgScript?action=ajax&rs=jQueryUpload::server";
		$upload_options = array(
			'script_url' => $url,
			'upload_dir' => "$wgUploadDirectory/jquery_upload_files/",
			'upload_url' => "$url&rsargs[]=",
			'delete_type' => 'POST',
			'max_file_size' => 50000000,
			'image_versions' => array(
				'thumbnail' => array(
					'upload_dir' => "$wgUploadDirectory/jquery_upload_thumb/",
					'upload_url' => "$url&rsargs[]=thumb&rsargs[]=",
					'max_width' => 80,
					'max_height' => 80
				)
			)
		);

		// Create the file upload object
		$upload_handler = new MWUploadHandler( $upload_options );

		// Call the appropriate method based on the request
		switch( $_SERVER['REQUEST_METHOD'] ) {
			case 'OPTIONS':
				break;
			case 'HEAD':
			case 'GET':
				$upload_handler->get();
				break;
			case 'POST':
				if( isset( $_REQUEST['_method'] ) && $_REQUEST['_method'] === 'DELETE' ) {
					$upload_handler->delete();
				} else {
					$upload_handler->post();
				}
				break;
			case 'DELETE':
				$upload_handler->delete();
				break;
			default:
				header( 'HTTP/1.1 405 Method Not Allowed' );
		}

		return '';
	}

	function head() {
		global $wgOut, $wgExtensionAssetsPath;
		$css = $wgExtensionAssetsPath . '/' . basename( dirname( __FILE__ ) ) . '/upload/css';

		// Bootstrap CSS Toolkit styles
		$wgOut->addStyle( 'http://blueimp.github.com/cdn/css/bootstrap.min.css', 'screen' );

		// Bootstrap styles for responsive website layout, supporting different screen sizes
		$wgOut->addStyle( 'http://blueimp.github.com/cdn/css/bootstrap-responsive.min.css', 'screen' );

		// CSS to style the file input field as button and adjust the Bootstrap progress bars
		$wgOut->addStyle( $css . '/jquery.fileupload-ui.css', 'screen' );

		// Bootstrap CSS fixes for IE6
		$wgOut->addHeadItem( 'IE6', "<!--[if lt IE 7]><link rel=\"stylesheet\" href=\"http://blueimp.github.com/cdn/css/bootstrap-ie6.min.css\"><![endif]-->\n" );

		// Shim to make HTML5 elements usable in older Internet Explorer versions
		$wgOut->addHeadItem( 'HTML5', "<!--[if lt IE 9]><script src=\"http://html5shim.googlecode.com/svn/trunk/html5.js\"></script><![endif]-->\n" );

	}

	function scripts() {
		global $wgExtensionAssetsPath;
		$base = $wgExtensionAssetsPath . '/' . basename( dirname( __FILE__ ) );
		$js = "$base/upload/js";
		return "<script src=\"$js/vendor/jquery.ui.widget.js\"></script>
		<!-- The Templates plugin is included to render the upload/download listings -->
		<script src=\"http://blueimp.github.com/JavaScript-Templates/tmpl.min.js\"></script>
		<!-- The Load Image plugin is included for the preview images and image resizing functionality -->
		<script src=\"http://blueimp.github.com/JavaScript-Load-Image/load-image.min.js\"></script>
		<!-- The Canvas to Blob plugin is included for image resizing functionality -->
		<script src=\"http://blueimp.github.com/JavaScript-Canvas-to-Blob/canvas-to-blob.min.js\"></script>
		<!-- Bootstrap JS and Bootstrap Image Gallery are not required, but included for the demo -->
		<script src=\"http://blueimp.github.com/cdn/js/bootstrap.min.js\"></script>
		<script src=\"http://blueimp.github.com/Bootstrap-Image-Gallery/js/bootstrap-image-gallery.min.js\"></script>
		<!-- The Iframe Transport is required for browsers without support for XHR file uploads -->
		<script src=\"$js/jquery.iframe-transport.js\"></script>
		<!-- The basic File Upload plugin -->
		<script src=\"$js/jquery.fileupload.js\"></script>
		<!-- The File Upload file processing plugin -->
		<script src=\"$js/jquery.fileupload-fp.js\"></script>
		<!-- The File Upload user interface plugin -->
		<script src=\"$js/jquery.fileupload-ui.js\"></script>
		<!-- The localization script -->
		<script src=\"$js/locale.js\"></script>
		<!-- The main application script -->
		<script src=\"$base/jqueryupload.js\"></script>
		<!-- The XDomainRequest Transport is included for cross-domain file deletion for IE8+ -->
		<!--[if gte IE 8]><script src=\"$js/cors/jquery.xdr-transport.js\"></script><![endif]-->
		<script>
			function uploadRenameBase(name) {
				var re = /^(.+)(\..+?)$/;
				var m = re.exec(name);
				return m[1];
			}
			function uploadRenameExt(name) {
				var re = /^(.+)(\..+?)$/;
				var m = re.exec(name);
				return m[2];
			}
		</script>";
	}

	function form() {
		global $wgScript;
		return '<form id="fileupload" action="' . $wgScript . '" method="POST" enctype="multipart/form-data">
			<!-- The fileupload-buttonbar contains buttons to add/delete files and start/cancel the upload -->
			<div class="row fileupload-buttonbar">
				<div class="span7">
					<!-- The fileinput-button span is used to style the file input field as button -->
					<span class="btn btn-success fileinput-button">
						<i class="icon-plus icon-white"></i>
						<span>Add files...</span>
						<input type="file" name="files[]" multiple>
					</span>
					<button type="submit" class="btn btn-primary start">
						<i class="icon-upload icon-white"></i>
						<span>Start upload</span>
					</button>
					<button type="reset" class="btn btn-warning cancel">
						<i class="icon-ban-circle icon-white"></i>
						<span>Cancel upload</span>
					</button>
					<button type="button" class="btn btn-danger delete">
						<i class="icon-trash icon-white"></i>
						<span>Delete</span>
					</button>
					<input type="checkbox" class="toggle">
				</div>
				<!-- The global progress information -->
				<div class="span5 fileupload-progress fade">
					<!-- The global progress bar -->
					<div class="progress progress-success progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
						<div class="bar" style="width:0%;"></div>
					</div>
					<!-- The extended global progress information -->
					<div class="progress-extended">&nbsp;</div>
				</div>
			</div>
			<!-- The loading indicator is shown during file processing -->
			<div class="fileupload-loading"></div>
			<br>
			<!-- The table listing the files available for upload/download -->
			<table role="presentation" class="table table-striped"><tbody class="files" data-toggle="modal-gallery" data-target="#modal-gallery"></tbody></table>
			<input type="hidden" name="mwaction" value="ajax" />
			<input type="hidden" name="rs" value="jQueryUpload::server" />
		</form>';
	}

	function templates() {
		return '<!-- The template to display files available for upload -->
		<script id="template-upload" type="text/x-tmpl">
		{% for (var i=0, file; file=o.files[i]; i++) { %}
			<tr class="template-upload fade">
				<td class="preview"><span class="fade"></span></td>
				<td class="name"><input type="hidden" name="upload_rename_from[]" value="{%=file.name%}" /><input type="text" name="upload_rename_to[]" value="{%=uploadRenameBase(file.name)%}" />{%=uploadRenameExt(file.name)%}</td>
				<td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
				{% if (file.error) { %}
					<td class="error" colspan="2"><span class="label label-important">{%=locale.fileupload.error%}</span> {%=locale.fileupload.errors[file.error] || file.error%}</td>
				{% } else if (o.files.valid && !i) { %}
					<td>
						<div class="progress progress-success progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="bar" style="width:0%;"></div></div>
					</td>
					<td class="start">{% if (!o.options.autoUpload) { %}
						<button class="btn btn-primary">
							<i class="icon-upload icon-white"></i>
							<span>{%=locale.fileupload.start%}</span>
						</button>
					{% } %}</td>
				{% } else { %}
					<td colspan="2"></td>
				{% } %}
				<td class="cancel">{% if (!i) { %}
					<button class="btn btn-warning">
						<i class="icon-ban-circle icon-white"></i>
						<span>{%=locale.fileupload.cancel%}</span>
					</button>
				{% } %}</td>
			</tr>
		{% } %}
		</script>
		<!-- The template to display files available for download -->
		<script id="template-download" type="text/x-tmpl">
		{% for (var i=0, file; file=o.files[i]; i++) { %}
			<tr class="template-download fade">
				{% if (file.error) { %}
					<td></td>
					<td class="name"><span>{%=file.name%}</span></td>
					<td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
					<td class="error" colspan="2"><span class="label label-important">{%=locale.fileupload.error%}</span> {%=locale.fileupload.errors[file.error] || file.error%}</td>
				{% } else { %}
					<td class="preview">{% if (file.thumbnail_url) { %}
						<a href="{%=file.url%}" title="{%=file.name%}" rel="gallery" download="{%=file.name%}"><img src="{%=file.thumbnail_url%}"></a>
					{% } %}</td>
					<td class="name">
						<a href="{%=file.url%}" title="{%=file.name%}" rel="{%=file.thumbnail_url&&\'gallery\'%}" download="{%=file.name%}">{%=file.name%}</a>
					</td>
					<td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
					<td colspan="2"></td>
				{% } %}
				<td class="delete">
					<button class="btn btn-danger" data-type="{%=file.delete_type%}" data-url="{%=file.delete_url%}">
						<i class="icon-trash icon-white"></i>
						<span>{%=locale.fileupload.destroy%}</span>
					</button>
					<input type="checkbox" name="delete" value="1">
				</td>
			</tr>
		{% } %}
		</script>';
	}

}

/**
 * A modified version of the UploadHandler class
 */
class MWUploadHandler extends UploadHandler {

	/**
	 * The delete URL needs to be adjusted because it doesn't check if the script URL already has a query-string
	 */
	protected function set_file_delete_url( $file ) {
		$file->delete_url = $this->options['script_url'] . '&file=' . rawurlencode($file->name);
		$file->delete_type = $this->options['delete_type'];
		if ($file->delete_type !== 'DELETE') $file->delete_url .= '&_method=DELETE';
	}

	/**
	 * We override the thumbnail creation to return a filetype icon when files can't be scaled as an image
	 */
	protected function create_scaled_image( $file, $options ) {
		if( $result = parent::create_scaled_image( $file, $options ) ) return $result;
		$icon = jQueryUpload::icon( $file );
		return symlink( $icon , $options['upload_dir'] . $file );
	}

}