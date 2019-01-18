/* External (WP) dependencies */
const { Dashicon, Panel, PanelBody, TextControl } = wp.components;
const { createElement } = wp.element;
const { __ } = wp.i18n;

const renderTraktForm = (
	<Panel header={ __( 'Trakt.tv Settings', 'traktivity' ) }>
		<PanelBody
			title={ __( 'Trakt.tv Settings', 'traktivity' ) }
			icon="dashicons-format-video"
			initialOpen={ true }
		>
			<div className="card">
				<p>
					{__(
						'To use the plugin, you will need to create an API application on Trakt.tv first.',
						'traktivity'
					)}
					<a
						href="https://trakt.tv/oauth/applications/new"
						title={ __( 'Click here to create that app.', 'traktivity' ) }
					>
						<Dashicon size={ 24 } icon="admin-links" />
					</a>
				</p>
				<p>
					<TextControl
						label={ __( 'Trakt.tv Username', 'traktivity' ) }
						value={__('username', 'traktivity') }
						// onChange={ className => setState({ className }) }
					/>
				</p>
			</div>
		</PanelBody>
	</Panel>
);

export default function TraktForm() {
	return createElement(
		'div',
		{
			className: 'card_list'
		},
		renderTraktForm
	);
}
