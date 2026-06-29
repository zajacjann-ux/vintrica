<?php
/**
 * Responsive HTML email layout for e-vignetta.eu messages.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Email_Template
 */
class Vintrica_Email_Template {

	/**
	 * Brand accent color.
	 */
	const ACCENT_COLOR = '#02B79C';

	/**
	 * Darker accent for hover states.
	 */
	const ACCENT_COLOR_HOVER = '#029882';

	/**
	 * Page background color.
	 */
	const BODY_BACKGROUND = '#F8FAFC';

	/**
	 * Card shadow for email clients that support it.
	 */
	const CARD_SHADOW = '0 1px 3px rgba(15,23,42,0.06), 0 1px 2px rgba(15,23,42,0.04)';

	/**
	 * Get optional email logo URL.
	 *
	 * @return string
	 */
	public static function get_logo_url() {
		/**
		 * Filter the logo URL used in HTML emails.
		 *
		 * @param string $logo_url Empty string when no logo is configured.
		 */
		$url = apply_filters( 'vintrica_email_logo_url', '' );

		return is_string( $url ) ? esc_url( $url ) : '';
	}

	/**
	 * Render email header markup.
	 *
	 * @return string
	 */
	public static function render_header() {
		$logo_url = self::get_logo_url();

		if ( '' !== $logo_url ) {
			return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#ffffff;">
<tr>
<td align="center" style="padding:28px 32px 24px;">
<img src="' . $logo_url . '" alt="e-vignetta.eu" style="display:block;max-width:220px;max-height:56px;width:auto;height:auto;border:0;" />
</td>
</tr>
</table>';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#ffffff;">
<tr>
<td align="center" style="padding:28px 32px 24px;">
<span style="display:inline-block;font-size:28px;font-weight:700;line-height:1.2;color:#111827;">e-vignetta.eu</span>
</td>
</tr>
</table>';
	}

	/**
	 * Render email footer markup.
	 *
	 * @return string
	 */
	public static function render_footer() {
		$year = esc_html( (string) gmdate( 'Y' ) );

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#ffffff;border-top:1px solid #e2e8f0;">
<tr>
<td align="center" style="padding:20px 32px 28px;">
<p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">&copy; ' . $year . ' e-vignetta.eu</p>
</td>
</tr>
</table>';
	}

	/**
	 * Render a complete HTML email document.
	 *
	 * @param string $preheader Preview text.
	 * @param string $content Inner HTML content.
	 * @return string
	 */
	public static function render_document( $preheader, $content ) {
		$preheader = esc_html( $preheader );

		return '<!DOCTYPE html>
<html lang="sk">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>e-vignetta.eu</title>
<!--[if mso]>
<style type="text/css">
body, table, td {font-family: Arial, sans-serif !important;}
</style>
<![endif]-->
<style type="text/css">
a.vintrica-email-button:hover {background-color:' . esc_attr( self::ACCENT_COLOR_HOVER ) . ' !important;}
</style>
</head>
<body style="margin:0;padding:0;background-color:' . esc_attr( self::BODY_BACKGROUND ) . ';font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#111827;">
<span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;">' . $preheader . '</span>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:' . esc_attr( self::BODY_BACKGROUND ) . ';padding:24px 12px;">
<tr>
<td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:640px;">
<tr><td>' . self::render_header() . '</td></tr>
<tr>
<td style="padding:24px 20px 8px;background-color:' . esc_attr( self::BODY_BACKGROUND ) . ';">' . $content . '</td>
</tr>
<tr><td>' . self::render_footer() . '</td></tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
	}

	/**
	 * Render a primary heading.
	 *
	 * @param string $text Heading text.
	 * @return string
	 */
	public static function render_heading( $text ) {
		return '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:700;color:#111827;">' . esc_html( $text ) . '</h1>';
	}

	/**
	 * Render a section title.
	 *
	 * @param string $text Section title.
	 * @return string
	 */
	public static function render_section_title( $text ) {
		return '<p style="margin:0 0 12px;font-size:14px;font-weight:700;color:#111827;">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Render a paragraph.
	 *
	 * @param string $text Paragraph text.
	 * @return string
	 */
	public static function render_paragraph( $text ) {
		return '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#475569;">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Render a status badge card.
	 *
	 * @param string $label Status label.
	 * @param string $value Status value.
	 * @return string
	 */
	public static function render_status_card( $label, $value ) {
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;background-color:#ffffff;border:1px solid #e2e8f0;border-left:4px solid ' . esc_attr( self::ACCENT_COLOR ) . ';border-radius:12px;box-shadow:' . esc_attr( self::CARD_SHADOW ) . ';">
<tr>
<td style="padding:16px 20px;">
<p style="margin:0 0 4px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:' . esc_attr( self::ACCENT_COLOR ) . ';">' . esc_html( $label ) . '</p>
<p style="margin:0;font-size:16px;font-weight:700;color:#111827;">' . esc_html( $value ) . '</p>
</td>
</tr>
</table>';
	}

	/**
	 * Render an info card with key-value rows.
	 *
	 * @param string               $title Card title.
	 * @param array<string,string> $rows  Label => value pairs.
	 * @return string
	 */
	public static function render_info_card( $title, array $rows ) {
		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:' . esc_attr( self::CARD_SHADOW ) . ';overflow:hidden;">
<tr>
<td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
<p style="margin:0;font-size:14px;font-weight:700;color:#111827;">' . esc_html( $title ) . '</p>
</td>
</tr>
<tr>
<td style="padding:12px 20px 16px;">';

		foreach ( $rows as $label => $value ) {
			$html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:8px 0 0;">
<tr>
<td style="padding:0;font-size:13px;color:#64748b;width:42%;vertical-align:top;">' . esc_html( $label ) . '</td>
<td style="padding:0;font-size:13px;color:#111827;font-weight:600;vertical-align:top;">' . esc_html( $value ) . '</td>
</tr>
</table>';
		}

		$html .= '</td></tr></table>';

		return $html;
	}

	/**
	 * Render a vignette card.
	 *
	 * @param string               $title Card title.
	 * @param array<string,string> $rows  Label => value pairs.
	 * @param string               $price Formatted price.
	 * @return string
	 */
	public static function render_vignette_card( $title, array $rows, $price ) {
		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 16px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:' . esc_attr( self::CARD_SHADOW ) . ';overflow:hidden;">
<tr>
<td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
<tr>
<td style="font-size:15px;font-weight:700;color:#111827;">' . esc_html( $title ) . '</td>
<td align="right" style="font-size:15px;font-weight:700;color:' . esc_attr( self::ACCENT_COLOR ) . ';white-space:nowrap;">' . esc_html( $price ) . '</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding:12px 20px 16px;">';

		foreach ( $rows as $label => $value ) {
			$html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 0;">
<tr>
<td style="padding:0;font-size:13px;color:#64748b;width:46%;vertical-align:top;">' . esc_html( $label ) . '</td>
<td style="padding:0;font-size:13px;color:#111827;vertical-align:top;">' . esc_html( $value ) . '</td>
</tr>
</table>';
		}

		$html .= '</td></tr></table>';

		return $html;
	}

	/**
	 * Render order totals summary.
	 *
	 * @param string $currency       Currency code.
	 * @param float  $subtotal       Subtotal amount.
	 * @param float  $service_fee    Service fee amount.
	 * @param float  $total          Total amount.
	 * @return string
	 */
	public static function render_totals_summary( $currency, $subtotal, $service_fee, $total ) {
		$rows = array(
			__( 'Medzisúčet', 'vintrica-vignette-form' ) => self::format_money( $currency, $subtotal ),
		);

		if ( $service_fee > 0 ) {
			$rows[ __( 'Servisný poplatok', 'vintrica-vignette-form' ) ] = self::format_money( $currency, $service_fee );
		}

		$rows[ __( 'Celková suma', 'vintrica-vignette-form' ) ] = self::format_money( $currency, $total );

		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:' . esc_attr( self::CARD_SHADOW ) . ';">
<tr>
<td style="padding:16px 20px;">';

		$row_count = count( $rows );
		$index     = 0;

		foreach ( $rows as $label => $value ) {
			++$index;
			$is_total    = ( $index === $row_count );
			$label_style = $is_total
				? 'padding:12px 0 0;font-size:15px;font-weight:700;color:#111827;border-top:1px solid #e2e8f0;'
				: 'padding:0 0 8px;font-size:14px;color:#64748b;';
			$value_style = $is_total
				? 'padding:12px 0 0;font-size:15px;font-weight:700;color:' . esc_attr( self::ACCENT_COLOR ) . ';border-top:1px solid #e2e8f0;text-align:right;'
				: 'padding:0 0 8px;font-size:14px;color:#111827;text-align:right;';

			$html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
<tr>
<td style="' . $label_style . '">' . esc_html( $label ) . '</td>
<td style="' . $value_style . '">' . esc_html( $value ) . '</td>
</tr>
</table>';
		}

		$html .= '</td></tr></table>';

		return $html;
	}

	/**
	 * Render a primary CTA button.
	 *
	 * @param string $url  Button URL.
	 * @param string $text Button label.
	 * @return string
	 */
	public static function render_button( $url, $text ) {
		$url = esc_url( $url );

		if ( '' === $url ) {
			return '';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 16px;">
<tr>
<td align="center">
<a href="' . $url . '" class="vintrica-email-button" style="display:inline-block;padding:14px 26px;background-color:' . esc_attr( self::ACCENT_COLOR ) . ';color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;border-radius:10px;">' . esc_html( $text ) . '</a>
</td>
</tr>
</table>';
	}

	/**
	 * Render a fallback payment URL block.
	 *
	 * @param string $url Checkout URL.
	 * @return string
	 */
	public static function render_payment_url( $url ) {
		$url = esc_url( $url );

		if ( '' === $url ) {
			return '';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:' . esc_attr( self::CARD_SHADOW ) . ';">
<tr>
<td style="padding:16px 20px;">
<p style="margin:0 0 8px;font-size:13px;color:#64748b;">' . esc_html__( 'Platobná adresa:', 'vintrica-vignette-form' ) . '</p>
<p style="margin:0;font-size:13px;line-height:1.6;word-break:break-all;"><a href="' . $url . '" style="color:' . esc_attr( self::ACCENT_COLOR ) . ';text-decoration:underline;">' . esc_html( $url ) . '</a></p>
</td>
</tr>
</table>';
	}

	/**
	 * Format a money value for email display.
	 *
	 * @param string $currency Currency code.
	 * @param float  $amount   Amount.
	 * @return string
	 */
	public static function format_money( $currency, $amount ) {
		return sanitize_text_field( $currency ) . ' ' . number_format_i18n( (float) $amount, 2 );
	}
}
