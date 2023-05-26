<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

$tabs = new CTabView();

$form = (new CForm())
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('template')))->removeId())
	->setId('templates-form')
	->setName('templatesForm');

if ($data['templateid'] !== null) {
	$form->addVar('templateid', $data['templateid']);
}

//$form->addVar('clear_templates', $data['clear_templates']);

// Template tab.
$template_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Template name'), 'template_name'))
			->addStyle('min-width: 150px;')
			->setAsteriskMark(),
		new CFormField(
			(new CTextBox('template_name', $data['template_name'], false, DB::getFieldLength('hosts', 'host')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		new CLabel(_('Visible name')),
		new CFormField(
			(new CTextBox('visiblename', $data['visible_name'], false, DB::getFieldLength('hosts', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	]);

$templates_field_items = [];
// todo - add data from JS
if ($data['linked_templates']) {
	$linked_templates= (new CTable())
		->setHeader([_('Name'), _('Action')])
		->setId('linked-templates')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

	foreach ($data['linked_templates'] as $template) {
		$linked_templates->addItem(
			(new CVar('templates['.$template['templateid'].']', $template['templateid']))->removeId()
		);

		if (array_key_exists($template['templateid'], $data['writable_templates'])) {
			$template_link = (new CLink(
					$template['name'],
					'templates.php?form=update&templateid='.$template['templateid']
				))
					->setTarget('_blank');
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$template_link->addClass(ZBX_STYLE_WORDWRAP);

		$clone_mode = $data['form'] === 'clone';

		$linked_templates->addRow([
			$template_link,
			(new CCol(
				new CHorList([
					(new CSimpleButton(_('Unlink')))
						->setAttribute('data-templateid', $template['templateid'])
						->onClick('
							submitFormWithParam("'.$form->getName().'", `unlink[${this.dataset.templateid}]`, 1);
						')
						->addClass(ZBX_STYLE_BTN_LINK),
					(array_key_exists($template['templateid'], $data['original_templates']) && !$clone_mode)
						? (new CSimpleButton(_('Unlink and clear')))
							->setAttribute('data-templateid', $template['templateid'])
							->onClick('
								submitFormWithParam("'.$form->getName().'",
									`unlink_and_clear[${this.dataset.templateid}]`, 1
								);
							')
							->addClass(ZBX_STYLE_BTN_LINK)
						: null
				])
			))->addClass(ZBX_STYLE_NOWRAP)
		], null, 'conditions_'.$template['templateid']);
	}

	$templates_field_items[] = $linked_templates;
}

$templates_field_items[] = (new CMultiSelect([
	'name' => 'add_templates[]',
	'object_name' => 'templates',
	'data' => $data['add_templates'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'templates',
			'srcfld1' => 'hostid',
			'srcfld2' => 'host',
			'dstfrm' => $form->getName(),
			'dstfld1' => 'add_templates_',
			'excludeids' => ($data['templateid'] == 0) ? [] : [$data['templateid']],
			'disableids' => array_column($data['linked_templates'], 'templateid')
		]
	]
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$template_tab
	->addItem([
		new CLabel(_('Templates'), 'add_templates__ms'),
		new CFormField(
			(count($templates_field_items) > 1)
			? (new CDiv($templates_field_items))->addClass('linked-templates')
			: $templates_field_items
		)
	])
	->addItem([
		(new CLabel(_('Template groups'), 'groups__ms'))->setAsteriskMark(),
		new CFormField(
			(new CMultiSelect([
			'name' => 'groups[]',
			'object_name' => 'templateGroup',
			'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
			'data' => $data['groups_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'groups_',
					'editable' => true,
					'disableids' => array_column($data['groups_ms'], 'id')
				]
			]
		]))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([
		new CLabel(_('Description')),
		new CFormField(
			(new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('hosts', 'description'))
		)
	]);

if ($data['vendor']) {
	$template_tab->addItem([
		new CLabel(_('Vendor and version')),
		new CFormField(implode(', ', [
			$data['vendor']['name'],
			$data['vendor']['version']
		]))
	]);
}

// Tags tab.
$tags =  new CPartial('configuration.tags.tab', [
	'source' => 'template',
	'tags' => $data['tags'],
	'readonly' => $data['readonly'],
	'tabs_id' => 'tabs',
	'tags_tab_id' => 'tags-tab'
]);

$form->addItem(
	(new CTemplateTag('tag-row-tmpl'))
		->addItem(renderTagTableRow('#{rowNum}', '', '', ZBX_TAG_MANUAL, ['add_post_js' => false]))
);

// Macros tab.
// todo - rewrite to form grid
$tmpl = $data['show_inherited_macros'] ? 'hostmacros.inherited.list.html' : 'hostmacros.list.html';

$macros_tab = (new CFormList('macrosFormList'))
	->addRow(null, (new CRadioButtonList('show_inherited_macros', (int) $data['show_inherited_macros']))
		->addValue(_('Template macros'), 0)
		->addValue(_('Inherited and template macros'), 1)
		->setModern()
	)
	->addRow(null, new CPartial($tmpl, [
		'macros' => $data['macros'],
		'readonly' => $data['readonly']
	]), 'macros_container');

// Value mapping tab.
$valuemap_tab = (new CFormList('valuemap-formlist'))->addRow(null,
	new CPartial('configuration.valuemap', [
		'source' => 'template',
		'valuemaps' => $data['valuemaps'],
		'readonly' => $data['readonly'],
		'form' => 'template'
	]));

if (!$data['readonly']) {
	$macro_row_tmpl = (new CTemplateTag('macro-row-tmpl'))
		->addItem(
			(new CRow([
				(new CCol([
					(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
						->addClass('macro')
						->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
						->setAttribute('placeholder', '{$MACRO}')
				]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false)
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
						->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
						->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
						->setAttribute('placeholder', _('description'))
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					(new CButton('macros[#{rowNum}][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_NOWRAP)
			]))
				->addClass('form_row')
		);

	$macro_row_inherited_tmpl = (new CTemplateTag('macro-row-tmpl-inherited'))
		->addItem(
			(new CRow([
				(new CCol([
					(new CTextAreaFlexible('macros[#{rowNum}][macro]', '', ['add_post_js' => false]))
						->addClass('macro')
						->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
						->setAttribute('placeholder', '{$MACRO}'),
					new CInput('hidden', 'macros[#{rowNum}][inherited_type]', ZBX_PROPERTY_OWN)
				]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{rowNum}]', '', false)
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
				(new CCol(
					(new CButton('macros[#{rowNum}][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_NOWRAP),
				[
					new CCol(
						(new CDiv())
							->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
							->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					),
					new CCol(),
					new CCol(
						(new CDiv())
							->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
							->setAdaptiveWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
					)
				]
			]))
				->addClass('form_row')
		)
		->addItem(
			(new CRow([
				(new CCol(
					(new CTextAreaFlexible('macros[#{rowNum}][description]', '', ['add_post_js' => false]))
						->setMaxlength(DB::getFieldLength('globalmacro', 'description'))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAttribute('placeholder', _('description'))
				))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT)->setColSpan(8)
			]))
				->addClass('form_row')
		);

	$form
		->addItem($macro_row_tmpl)
		->addItem($macro_row_inherited_tmpl);
}


if ($data['templateid']) {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'template_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'template_edit_popup.clone('.json_encode([
					'title' => _('New template'),
					'buttons' => [
						[
							'title' => _('Add'),
							'class' => 'js-add',
							'keepOpen' => true,
							'isSubmit' => true,
							'action' => 'template_edit_popup.submit();'
						],
						[
							'title' => _('Cancel'),
							'class' => ZBX_STYLE_BTN_ALT,
							'cancel' => true,
							'action' => ''
						]
					]
				]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete template?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'template_edit_popup.delete();'
		],
		[
			'title' => _('Delete and clear'),
			'confirmation' => _('Delete and clear template? (Warning: all linked hosts will be cleared!)'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'template_edit_popup.delete('.json_encode(['clear' => true]).');'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'template_edit_popup.submit();'
		]
	];
}

$tabs
	->addTab('tmplTab', _('Templates'), $template_tab)
	->addTab('tags-tab', _('Tags'), $tags, TAB_INDICATOR_TAGS)
	->addTab('macroTab', _('Macros'),$macros_tab, TAB_INDICATOR_MACROS)
	->addTab('valuemap-tab', _('Value mapping'), $valuemap_tab, TAB_INDICATOR_VALUEMAPS)
	->setSelected(0);

$form
	->addItem($tabs)
	->addItem(
		(new CScriptTag('
			template_edit_popup.init('.json_encode([
				'data' => $data,
			], JSON_THROW_ON_ERROR).');
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['templateid'] === null ? _('New template') : _('Template'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_TEMPLATES_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('template.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
