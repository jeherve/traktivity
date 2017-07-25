/* External dependencies */
import React from 'react';
import Gridicon from 'gridicons';

class Notice extends React.Component {
	constructor() {
		super();

		this.icon = this.icon.bind(this);
	}

	icon(type) {
		let iconType;

		// Get the current state.
		switch( type ) {
			case 'success':
				iconType = 'checkmark';
				break;
			case 'error':
				iconType = 'notice';
				break;
			case 'progress':
				iconType = 'info';
				break;
			default:
				iconType = 'info';
		}

		return iconType;
	}

	componentWillUnmount() {
		// Make sure notices are cleared from the state when the component is removed from the DOM.
		this.props.removeNotice();
	}

	render() {
		// Empty notice? Do not show anything.
		if ( ! this.props.notice ) {
			return <div className="message empty"></div>;
		}
		return (
			<div className={`message traktivity__${this.props.notice.type}`}>
				<div className="message_content">
					<Gridicon className="notice_icon" icon={this.icon(this.props.notice.type)} size={ 24 } />
					<span className="notice_text">{this.props.notice.message}</span>

					<span className="dismiss">
						<Gridicon
							className="dismiss_icon"
							icon="cross"
							size={ 24 }
							onClick={() => this.props.removeNotice()}
						/>
						<span className="screen-reader-text">Dismiss this message.</span>
					</span>
				</div>
			</div>
		)
	}
}

export default Notice;
