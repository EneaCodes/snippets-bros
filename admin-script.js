jQuery(document).ready(function($) {
    'use strict';

    $('#snippets-bros-select-all').on('change', function() {
        $('.snippet-checkbox').prop('checked', this.checked);
    });

    function handleBulkAction(action, ids, $button) {
        if (action === 'export') {
            exportSnippets(ids);
            return;
        }

        if (action === 'delete' && !confirm('Delete ' + ids.length + ' snippet(s)?')) {
            return;
        }

        $button.prop('disabled', true).text('Processing...');

        $.post(snippetsBros.ajaxurl, {
            action: 'snippets_bros_bulk_action',
            nonce: snippetsBros.nonce,
            bulk_action: action,
            snippet_ids: ids
        }, function(response) {
            if (response.success) {
                window.scrollTo(0, 0);
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
                $button.prop('disabled', false).text('Apply');
            }
        }).fail(function() {
            alert('Request failed. Please try again.');
            $button.prop('disabled', false).text('Apply');
        });
    }

    $('#bulk-action-apply, #bulk-action-apply-top').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var action = $(this).closest('.snippets-bros-bulk-actions').find('select').val();
        var ids = $('.snippet-checkbox:checked').map(function() { return this.value; }).get();

        if (!action || ids.length === 0) {
            alert('Please select an action and at least one snippet.');
            return;
        }

        handleBulkAction(action, ids, $button);
    });

    $(document).on('click', '.export-single', function() {
        exportSnippets([$(this).data('snippet-id')]);
    });

    function exportSnippets(ids) {
        if (ids.length === 0) return alert('No snippet selected.');

        $.post(snippetsBros.ajaxurl, {
            action: 'snippets_bros_export_snippet',
            nonce: snippetsBros.export_nonce,
            snippet_ids: ids
        }, function(res) {
            if (res.success) {
                downloadJSON(res.data.content, res.data.filename);
            } else {
                alert('Export failed: ' + (res.data || 'Unknown error'));
            }
        }).fail(function() {
            alert('Export request failed.');
        });
    }

    function downloadJSON(content, filename) {
        var blob = new Blob([content], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'snippet.json';
        a.click();
        URL.revokeObjectURL(url);
    }

    $('#clear-error-log').on('click', function() {
        if (!confirm('Clear all error logs? This cannot be undone.')) return;

        var $btn = $(this).prop('disabled', true).text('Clearing...');

        $.post(snippetsBros.ajaxurl, {
            action: 'snippets_bros_clear_error_log',
            nonce: snippetsBros.clear_log_nonce
        }, function(res) {
            if (res.success) {
                window.scrollTo(0, 0);
                location.reload();
            } else {
                alert('Failed: ' + (res.data || 'Unknown error'));
                $btn.prop('disabled', false).text('Clear Log');
            }
        }).fail(function() {
            alert('Request failed.');
            $btn.prop('disabled', false).text('Clear Log');
        });
    });

    function initializeCodeMirror() {
        if (typeof wp === 'undefined' || typeof wp.codeEditor === 'undefined') {
            return;
        }

        var settings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};

        settings.codemirror = _.extend({}, settings.codemirror, {
            indentUnit: 4,
            tabSize: 4,
            indentWithTabs: false,
            lineNumbers: true,
            lineWrapping: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            styleActiveLine: true,
            showTrailingSpace: true,
            foldGutter: true,
            gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
            mode: 'application/x-httpd-php',
            extraKeys: {
                "Ctrl-Space": "autocomplete",
                "Ctrl-/": "toggleComment",
                "Cmd-/": "toggleComment",
                "Tab": function(cm) {
                    return cm.somethingSelected() ? cm.indentSelection("add") : cm.replaceSelection("    ", "end");
                },
                "Shift-Tab": function(cm) {
                    return cm.indentSelection("subtract");
                }
            },
            viewportMargin: 12,
            scrollbarStyle: "native"
        });

        var editor = wp.codeEditor.initialize($('#snippets_bros_content'), settings);

        if (editor && editor.codemirror) {
            var cm = editor.codemirror;

            cm.on('changes', function() {
                cm.refresh();
            });

            $(window).on('resize', function() {
                var scroll = $(window).scrollTop();
                requestAnimationFrame(function() {
                    cm.refresh();
                    $(window).scrollTop(scroll);
                });
            });
        }
    }

    initializeCodeMirror();

    $(document).on('click', '.safe-mode-toggle', function() {
        var $button = $(this);
        var originalText = $button.html();
        $button.html('<span class="dashicons dashicons-update spin"></span> Processing...').prop('disabled', true);
        setTimeout(function() {
            $button.html(originalText).prop('disabled', false);
        }, 2000);
    });

    if (snippetsBros.safe_mode_enabled) {
        $('body').addClass('snippets-bros-safe-mode');
    }

    $(document).on('click', 'a[href*="snippets_bros_emergency"]', function(e) {
        if (!confirm('⚠️ EMERGENCY RECOVERY\n\nThis will:\n1. Enable Safe Mode\n2. Disable ALL snippets\n3. Clear error logs\n\nUse this only if your site is crashing due to a snippet.\n\nContinue?')) {
            e.preventDefault();
        }
    });

    $('form[enctype="multipart/form-data"]').on('submit', function(e) {
        var fileInput = $(this).find('input[type="file"]');
        var file = fileInput[0].files[0];
        
        if (file) {
            if (file.size > 10 * 1024 * 1024) {
                alert('Error: File is too large. Maximum file size is 10MB.');
                e.preventDefault();
                return false;
            }
            
            if (!file.name.toLowerCase().endsWith('.json')) {
                alert('Error: Invalid file type. Please upload a JSON file.');
                e.preventDefault();
                return false;
            }
        }
    });

    $('#snippet-form').on('submit', function() {
        var type = $('#snippets_bros_type').val();
        var content = $('#snippets_bros_content').val();
        
        if (type === 'php' && content.trim() !== '') {
            var dangerousFunctions = [
                'eval(',
                'system(',
                'shell_exec(',
                'exec(',
                'passthru(',
                'proc_open(',
                'popen(',
                'pcntl_exec(',
                'dl(',
                'ini_alter('
            ];
            
            var lowerContent = content.toLowerCase();
            for (var i = 0; i < dangerousFunctions.length; i++) {
                if (lowerContent.includes(dangerousFunctions[i])) {
                    if (!confirm('⚠️ WARNING: Your PHP code contains potentially dangerous function: ' + dangerousFunctions[i] + '\n\nThis could crash your site or create security vulnerabilities.\n\nAre you sure you want to save this snippet?')) {
                        return false;
                    }
                    break;
                }
            }
        }
        
        var $submitButton = $(this).find('.button-primary');
        var originalText = $submitButton.text();
        $submitButton.text('Saving...').prop('disabled', true);
        setTimeout(function() {
            $submitButton.text(originalText).prop('disabled', false);
        }, 5000);
    });

    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            var $checked = $('.snippet-checkbox:checked');
            if ($checked.length === 1) {
                var editUrl = $('a[href*="edit=' + $checked.val() + '"]').attr('href');
                if (editUrl) {
                    window.location.href = editUrl;
                    e.preventDefault();
                }
            }
        }
        
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            var $checked = $('.snippet-checkbox:checked');
            if ($checked.length === 1) {
                var cloneUrl = $('a[href*="snippets_bros_action=clone"][href*="id=' + $checked.val() + '"]').attr('href');
                if (cloneUrl) {
                    if (confirm('Clone this snippet?')) {
                        window.location.href = cloneUrl;
                        e.preventDefault();
                    }
                }
            }
        }
    });

    $('#snippets-search').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#snippets-tbody tr').each(function() {
            var snippetName = $(this).data('snippet-name') || '';
            var snippetText = $(this).text().toLowerCase();
            var isVisible = snippetName.includes(value) || snippetText.includes(value);
            $(this).toggle(isVisible);
        });
    });

    $('<style>').text('.dashicons.spin { animation: spin 1s linear infinite; } @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }').appendTo('head');
});