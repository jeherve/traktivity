/* External (WP) dependencies */
const { createElement } = wp.element;
const { __ } = wp.i18n;

const renderHeader = (
	<div className="header_items">
		<h1>{__( 'Traktivity dashboard', 'traktivity' )}</h1>
	</div>
);

export default function Header() {
	return createElement(
		'header',
		{
			className: 'top'
		},
		renderHeader
	);
}
