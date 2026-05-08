// admin-messages-templates.js
// 管理者お知らせ画面: テンプレート適用・編集UI

(function() {
    'use strict';

    const dataScript = document.getElementById('admin-messages-templates-data');
    if (!dataScript) {
        return;
    }

    let pageData;
    try {
        pageData = JSON.parse(dataScript.textContent || '{}');
    } catch (error) {
        return;
    }

    function asKeyedObject(raw) {
        if (!raw || typeof raw !== 'object') {
            return {};
        }
        if (Array.isArray(raw)) {
            return {};
        }
        return raw;
    }

    const templates = asKeyedObject(pageData.templates);
    const editableTemplates = asKeyedObject(pageData.editableTemplates);
    const templateCreateLabel = pageData.templateCreateLabel || '作成';
    const templateUpdateLabel = pageData.templateUpdateLabel || '更新';

    function applyTemplate(templateKey) {
        if (!templateKey || !templates[templateKey]) {
            return;
        }

        const template = templates[templateKey];
        const titleJaField = document.getElementById('title_ja');
        const titleEnField = document.getElementById('title_en');
        const bodyJaField = document.getElementById('body_ja');
        const bodyEnField = document.getElementById('body_en');

        if (titleJaField) {
            titleJaField.value = template.title_ja != null ? String(template.title_ja) : '';
        }
        if (titleEnField) {
            titleEnField.value = template.title_en != null ? String(template.title_en) : '';
        }
        if (bodyJaField) {
            bodyJaField.value = template.body_ja != null ? String(template.body_ja) : '';
        }
        if (bodyEnField) {
            bodyEnField.value = template.body_en != null ? String(template.body_en) : '';
        }
    }

    function syncSendTemplateKey() {
        const select = document.getElementById('message-template-select');
        const hidden = document.getElementById('template_key');
        if (select && hidden) {
            hidden.value = select.value || '';
        }
    }

    function resetTemplateEditor() {
        const idEl = document.getElementById('template_editor_id');
        const nameEl = document.getElementById('template_editor_name');
        const titleJaEl = document.getElementById('template_editor_title_ja');
        const titleEnEl = document.getElementById('template_editor_title_en');
        const bodyJaEl = document.getElementById('template_editor_body_ja');
        const bodyEnEl = document.getElementById('template_editor_body_en');
        const deleteIdEl = document.getElementById('template_delete_id');
        const deleteBtn = document.getElementById('template_editor_delete_button');
        const saveBtn = document.getElementById('template_editor_save_button');

        if (idEl) idEl.value = '';
        if (nameEl) nameEl.value = '';
        if (titleJaEl) titleJaEl.value = '';
        if (titleEnEl) titleEnEl.value = '';
        if (bodyJaEl) bodyJaEl.value = '';
        if (bodyEnEl) bodyEnEl.value = '';
        if (deleteIdEl) deleteIdEl.value = '';
        if (deleteBtn) deleteBtn.disabled = true;
        if (saveBtn) saveBtn.textContent = templateCreateLabel;
    }

    function loadTemplateToEditor(templateId) {
        const template = editableTemplates[String(templateId)];
        if (!template) {
            resetTemplateEditor();
            return;
        }

        const idEl = document.getElementById('template_editor_id');
        const nameEl = document.getElementById('template_editor_name');
        const titleJaEl = document.getElementById('template_editor_title_ja');
        const titleEnEl = document.getElementById('template_editor_title_en');
        const bodyJaEl = document.getElementById('template_editor_body_ja');
        const bodyEnEl = document.getElementById('template_editor_body_en');
        const deleteIdEl = document.getElementById('template_delete_id');
        const deleteBtn = document.getElementById('template_editor_delete_button');
        const saveBtn = document.getElementById('template_editor_save_button');

        if (idEl) idEl.value = template.id;
        if (nameEl) nameEl.value = template.name || '';
        if (titleJaEl) titleJaEl.value = template.title_ja || '';
        if (titleEnEl) titleEnEl.value = template.title_en || '';
        if (bodyJaEl) bodyJaEl.value = template.body_ja || '';
        if (bodyEnEl) bodyEnEl.value = template.body_en || '';
        if (deleteIdEl) deleteIdEl.value = template.id;
        if (deleteBtn) deleteBtn.disabled = false;
        if (saveBtn) saveBtn.textContent = templateUpdateLabel;
    }

    function toggleTargetExtra() {
        const type = document.getElementById('target_type')?.value;
        const filteredEl = document.getElementById('target_filtered_fields');
        const specificEl = document.getElementById('target_specific_fields');
        const adminHint = document.getElementById('target_admin_only_hint');
        if (filteredEl) {
            filteredEl.classList.toggle('is-visible', type === 'filtered');
        }
        if (specificEl) {
            specificEl.classList.toggle('is-visible', type === 'specific');
        }
        if (adminHint) {
            adminHint.classList.toggle('is-visible', type === 'admin_users_only');
        }
        const ri = document.getElementById('recipient_identifiers');
        if (ri) {
            ri.required = (type === 'specific');
        }
    }

    function initCollapsiblePanels() {
        document.querySelectorAll('.admin-collapsible-toggle').forEach(function(toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const panel = document.getElementById(toggleBtn.dataset.targetId);
                if (!panel) {
                    return;
                }
                const isOpen = panel.classList.toggle('is-open');
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    }

    function initialize() {
        const templateSelect = document.getElementById('message-template-select');
        if (templateSelect) {
            templateSelect.addEventListener('change', function() {
                applyTemplate(this.value);
                syncSendTemplateKey();
            });
        }
        syncSendTemplateKey();

        const editorSelect = document.getElementById('template-editor-select');
        if (editorSelect) {
            editorSelect.addEventListener('change', function() {
                if (this.value === 'new') {
                    resetTemplateEditor();
                } else {
                    loadTemplateToEditor(this.value);
                }
            });
            if (editorSelect.value && editorSelect.value !== 'new') {
                loadTemplateToEditor(editorSelect.value);
            } else {
                resetTemplateEditor();
            }
        }

        const targetType = document.getElementById('target_type');
        if (targetType) {
            targetType.addEventListener('change', toggleTargetExtra);
            toggleTargetExtra();
        }

        initCollapsiblePanels();
        window.applyTemplate = applyTemplate;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
