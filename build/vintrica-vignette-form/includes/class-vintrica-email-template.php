<?php
/**
 * Responsive HTML email layout for VINTRICA customer messages.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Email_Template
 */
class Vintrica_Email_Template {

	/**
	 * VINTRICA brand accent color.
	 */
	const ACCENT_COLOR = '#4f46e5';

	/**
	 * Render a complete HTML email document.
	 *
	 * @param string $preheader Preview text.
	 * @param string $content Inner HTML content.
	 * @return string
	 */
	public static function render_document( $preheader, $content ) {
		$preheader = esc_html( $preheader );
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$year      = esc_html( (string) gmdate( 'Y' ) );

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
</head>
<body style="margin:0;padding:0;background-color:#f6f8fb;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#0f172a;">
<span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;">' . $preheader . '</span>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f6f8fb;padding:24px 12px;">
<tr>
<td align="center">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:640px;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">
<tr>
<td style="background-color:' . esc_attr( self::ACCENT_COLOR ) . ';padding:28px 32px;text-align:center;">
<span style="display:inline-block;font-size:24px;font-weight:700;letter-spacing:0.08em;color:#ffffff;">e-vignetta.eu</span>
</td>
</tr>
<tr>
<td style="padding:32px;">' . $content . '</td>
</tr>
<tr>
<td style="padding:20px 32px 28px;border-top:1px solid #e2e8f0;background-color:#f8fafc;text-align:center;">
<p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;">&copy; ' . $year . ' ' . $site_name . ' · e-vignetta.eu</p>
</td>
</tr>
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
		return '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:700;color:#0f172a;">' . esc_html( $text ) . '</h1>';
	}

	/**
	 * Render a paragraph.
	 *
	 * @param string $text Paragraph text.
	 * @return string
	 */
	public static function render_paragraph( $text ) {
		return '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#334155;">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Render a status badge card.
	 *
	 * @param string $label Status label.
	 * @param string $value Status value.
	 * @return string
	 */
	public static function render_status_card( $label, $value ) {
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;background-color:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;">
<tr>
<td style="padding:16px 20px;">
<p style="margin:0 0 4px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#4338ca;">' . esc_html( $label ) . '</p>
<p style="margin:0;font-size:16px;font-weight:700;color:#0f172a;">' . esc_html( $value ) . '</p>
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
		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
<tr>
<td style="padding:16px 20px;background-color:#f8fafc;border-bottom:1px solid #e2e8f0;">
<p style="margin:0;font-size:14px;font-weight:700;color:#0f172a;">' . esc_html( $title ) . '</p>
</td>
</tr>
<tr>
<td style="padding:8px 20px 16px;">';

		foreach ( $rows as $label => $value ) {
			$html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:8px 0 0;">
<tr>
<td style="padding:0;font-size:13px;color:#64748b;width:42%;vertical-align:top;">' . esc_html( $label ) . '</td>
<td style="padding:0;font-size:13px;color:#0f172a;font-weight:600;vertical-align:top;">' . esc_html( $value ) . '</td>
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
		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 16px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
<tr>
<td style="padding:16px 20px;background-color:#111827;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
<tr>
<td style="font-size:15px;font-weight:700;color:#ffffff;">' . esc_html( $title ) . '</td>
<td align="right" style="font-size:15px;font-weight:700;color:#ffffff;white-space:nowrap;">' . esc_html( $price ) . '</td>
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
<td style="padding:0;font-size:13px;color:#0f172a;vertical-align:top;">' . esc_html( $value ) . '</td>
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

		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
<tr>
<td style="padding:16px 20px;">';

		$row_count = count( $rows );
		$index     = 0;

		foreach ( $rows as $label => $value ) {
			++$index;
			$is_total = ( $index === $row_count );
			$label_style = $is_total
				? 'padding:12px 0 0;font-size:15px;font-weight:700;color:#0f172a;border-top:1px solid #e2e8f0;'
				: 'padding:0 0 8px;font-size:14px;color:#64748b;';
			$value_style = $is_total
				? 'padding:12px 0 0;font-size:15px;font-weight:700;color:' . esc_attr( self::ACCENT_COLOR ) . ';border-top:1px solid #e2e8f0;text-align:right;'
				: 'padding:0 0 8px;font-size:14px;color:#0f172a;text-align:right;';

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
<a href="' . $url . '" style="display:inline-block;padding:16px 32px;background-color:' . esc_attr( self::ACCENT_COLOR ) . ';color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;border-radius:10px;">' . esc_html( $text ) . '</a>
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

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
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
