/**
 * WordPress dependencies
 */
import { Notice as WPNotice } from '@wordpress/components';

/**
 * A dismissible status message.
 *
 * @param {Object}   props
 * @param {Object}   [props.notice]     The { status, message } to show, if any.
 * @param {Function} props.removeNotice Clears the notice.
 * @return {?Element} The notice, or nothing when there is none.
 */
export default function Notice( { notice, removeNotice } ) {
	if ( ! notice ) {
		return null;
	}

	return (
		<WPNotice status={ notice.status } onRemove={ removeNotice }>
			{ notice.message }
		</WPNotice>
	);
}
