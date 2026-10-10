import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';
import UrlField from './UrlField';
import EmailField from './EmailField';
import MediaField from './MediaField';
import PhoneField from './PhoneField';
import DateField from './DateField';
import DateTimeField from './DateTimeField';
import EventLocationField from './EventLocationField';
import SelectField from './SelectField';
import TextareaField from './TextareaField';
import SameAsBuilder from './SameAsBuilder';
import { OfferField, ReturnPolicyField, ShippingField } from './CommerceFields';
import {
	AddressField,
	LatLongField,
	HoursField,
	AggregateRatingField,
	ReviewField,
} from './LocalBusinessFields';

/**
 * Renders the correct shared field component for a given field definition
 * (CLAUDE.md Section 10.6.A: "do not let each surface implement its own
 * version of a URL input, date input, etc."). Used by both EntityForm and
 * PostBlockForm so the wizard, Entity Settings, and post editor panel all
 * render fields identically.
 */
export default function TypedField( { fieldKey, def, value, onChange } ) {
	const required = !! def.required;
	// Registry entries may carry a translated label; otherwise fall back to
	// the schema.org property name.
	// "Recommended" is conveyed as text next to the label, never color alone.
	const baseLabel = def.label || fieldKey;
	const label = def.recommended ? (
		<span className="schema-field-label-wrap">
			{ baseLabel }
			<span className="schema-recommended-mark">{ __( 'Recommended', 'schema-plugin' ) }</span>
		</span>
	) : (
		baseLabel
	);

	switch ( def.type ) {
		case 'url':
			return <UrlField label={ label } value={ value } onChange={ onChange } required={ required } />;

		case 'email':
			return <EmailField label={ label } value={ value } onChange={ onChange } required={ required } />;

		case 'media':
			return <MediaField label={ label } value={ value } onChange={ onChange } required={ required } />;

		case 'tel':
			return <PhoneField label={ label } value={ value } onChange={ onChange } required={ required } />;

		case 'date':
			return <DateField label={ label } value={ value } onChange={ onChange } required={ required } />;

		case 'select':
			return (
				<SelectField
					label={ label }
					value={ value }
					options={ def.options || [] }
					onChange={ onChange }
					required={ required }
				/>
			);

		case 'textarea':
			return <TextareaField label={ label } value={ value } onChange={ onChange } required={ required } />;

		case 'datetime':
			return <DateTimeField label={ label } value={ value } onChange={ onChange } required={ required } />;

		case 'event_location':
			return <EventLocationField value={ value } onChange={ onChange } required={ required } />;

		case 'offer':
			return <OfferField label={ label } value={ value } onChange={ onChange } />;

		case 'return_policy':
			return <ReturnPolicyField label={ label } value={ value } onChange={ onChange } />;

		case 'shipping_service':
			return <ShippingField label={ label } value={ value } onChange={ onChange } />;

		case 'sameAs_list':
			return <SameAsBuilder value={ value } onChange={ onChange } />;

		case 'address':
			return <AddressField value={ value } onChange={ onChange } required={ required } />;

		case 'lat_long':
			return <LatLongField value={ value } onChange={ onChange } />;

		case 'repeatable_hours':
			return <HoursField value={ value } onChange={ onChange } />;

		case 'aggregate_rating':
			return <AggregateRatingField value={ value } onChange={ onChange } />;

		case 'review':
			return <ReviewField value={ value } onChange={ onChange } />;

		case 'text':
		default:
			return (
				<TextControl
					label={ withRequiredMark( label, required ) }
					value={ value || '' }
					onChange={ onChange }
				/>
			);
	}
}
