/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import { Icon, check, error, info, close } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

const ICONS = {
	success: check,
	error,
	progress: info,
};

class Notice extends Component {
	componentWillUnmount() {
		// Make sure notices are cleared from the state when the component is removed from the DOM.
		this.props.removeNotice();
	}

	render() {
		const { notice, removeNotice } = this.props;

		// Empty notice? Do not show anything.
		if ( ! notice ) {
			return <div className="message empty" />;
		}

		return (
			<div className={ `message traktivity__${ notice.type }` }>
				<div className="message_content">
					<Icon
						className="notice_icon"
						icon={ ICONS[ notice.type ] || info }
						size={ 24 }
					/>
					<span className="notice_text">{ notice.message }</span>

					<button
						type="button"
						className="dismiss"
						onClick={ removeNotice }
					>
						<Icon
							className="dismiss_icon"
							icon={ close }
							size={ 24 }
						/>
						<span className="screen-reader-text">
							{ __( 'Dismiss this message.', 'traktivity' ) }
						</span>
					</button>
				</div>
			</div>
		);
	}
}

export default Notice;
