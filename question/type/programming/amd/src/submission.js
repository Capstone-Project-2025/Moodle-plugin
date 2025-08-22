/* eslint-disable */
define('qtype_programming/submission', ['jquery'], function($) {
    return {
        init: function(params) {
            let attemptCount = 0;
            const maxAttempts = 30;

            const waitForElement = setInterval(function() {
                attemptCount++;
                const inputEl = document.getElementById(params.inputId);

                if (inputEl) {
                    clearInterval(waitForElement);

                    const editor = window['codemirrorEditor_' + params.inputId] || {
                        getValue: function() {
                            return inputEl.value;
                        }
                    };

                    // 🔧 Handle result display (used during polling)
                    function handleSubmissionResult(res) {
                        let explanation;
                        switch (res.result) {
                            case 'AC':
                                explanation = "<span style='color:green; font-weight:bold;'>✅ Accepted</span>"; break;
                            case 'WA':
                                explanation = "<span style='color:red; font-weight:bold;'>❌ Wrong Answer</span>"; break;
                            case 'TLE':
                                explanation = "<span style='color:orange; font-weight:bold;'>⏱️ Time Limit Exceeded</span>"; break;
                            case 'MLE':
                                explanation = "<span style='color:purple; font-weight:bold;'>💾 Memory Limit Exceeded</span>"; break;
                            case 'RE':
                                explanation = "<span style='color:brown; font-weight:bold;'>💥 Runtime Error</span>"; break;
                            case 'CE':
                                explanation = "<span style='color:gray; font-weight:bold;'>⚙️ Compilation Error</span>"; break;
                            default:
                                explanation = "<span style='color:red;'>❌ Unknown result: " + (res.result || 'N/A') + "</span>";
                        }

                        resultDiv.append(
                            '<br><strong>Language:</strong> ' + res.language +
                            '<br><strong>Time:</strong> ' + (res.time ?? '-') +
                            '<br><strong>Memory:</strong> ' + (res.memory ?? '-') +
                            '<br>' + explanation
                        );

                        if (res.cases?.length > 0) {
                            let caseDetails = '<br><br><strong>Test Cases:</strong><ul style="padding-left: 1.5em;">';

                            res.cases.forEach(c => {
                                const symbol = c.status === 'AC' ? '✅' : '❌';
                                const points = (typeof c.points !== 'undefined' && typeof c.total !== 'undefined')
                                    ? `${c.points} / ${c.total} points`
                                    : 'no score';
                                const time = c.time ?? '-';
                                const memory = c.memory ?? '-';

                                caseDetails += `
                                    <li>
                                        ${symbol} Case ${c.case_id}: ${points}
                                        <span style="font-size: 90%; color: #666;">
                                            (Time: ${time}s, Mem: ${memory} KB)
                                        </span>
                                    </li>`;
                            });

                            caseDetails += '</ul>';

                            const totalPoints = res.case_points ?? 0;
                            const totalTotal = res.case_total ?? 0;

                            resultDiv.append(`
                                <br><strong>Total:</strong> ${totalPoints} / ${totalTotal} points
                                ${caseDetails}
                            `);
                        }
                    }

                    // 🔁 Polling the submission result
                    function pollSubmission(submissionId, tries = 0) {
                        fetch(params.resultUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ submission_id: submissionId })
                        })
                        .then(res => res.json())
                        .then(res => {
                            if (res.result === 'unknown' || res.status === 'P') {
                                if (tries < 10) {
                                    setTimeout(() => pollSubmission(submissionId, tries + 1), 3000);
                                } else {
                                    resultDiv.append('<br><span style="color:red;">❌ Still no result after waiting</span>');
                                }
                            } else {
                                handleSubmissionResult(res);
                            }
                        })
                        .catch(err => {
                            resultDiv.append('<br><span style="color:red;">❌ Error polling: ' + err.message + '</span>');
                        });
                    }

                    // ✅ Submit the solution
                    $('#' + params.submitButtonId).on('click', function() {
                        if (editor && typeof editor.save === 'function') {
                            editor.save(); // met à jour le DOM
                        }
                        const code = inputEl.value; // contient le texte après .save()


                        const languageId = parseInt($('#' + params.selectId).val(), 10);
                        resultDiv = $('#' + params.resultContainerId);
                        resultDiv.html('Submitting...');

                        fetch(params.submitUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                code: code,
                                problemcode: params.problemCode,
                                language: languageId,
                                sesskey: params.sesskey,
                                questionid: params.questionId,
                                attemptid: params.attemptid
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.submission_id) {
                                resultDiv.html(
                                    '<strong>Submission ID:</strong> ' + data.submission_id +
                                    '<br>Checking result...'
                                );

                                const hiddenField = document.querySelector('input[name="' + params.submissionIdName + '"]');
                                if (hiddenField) {
                                    hiddenField.value = data.submission_id;
                                }

                                const answerField = document.querySelector('textarea[name="' + params.inputname + '"]');
                                if (answerField) {
                                    answerField.value = answerField.value.trim() + ' // submission #' + data.submission_id;
                                }

                                pollSubmission(data.submission_id);
                            } else {
                                throw new Error(data.error || 'Unknown error');
                            }
                        })
                        .catch(function(err) {
                            resultDiv.html('<span style="color:red;">Submission failed: ' + err + '</span>');
                        });
                    });

                    // ✅ Show previous submissions
                    $('#' + params.showSubmissionsButtonId).on('click', function() {
                        const listDiv = $('#' + params.submissionListContainerId);
                        const button = $(this);

                        if (listDiv.is(':empty')) {
                        listDiv.html('<span style="color:blue;">⏳ Loading submissions...</span>');

                        const url = new URL(params.submissionListUrl);
                        url.searchParams.append('questionid', params.questionId);
                        url.searchParams.append('sesskey', params.sesskey);
                        url.searchParams.append('attemptid', params.attemptid);

                        fetch(url.toString(), {
                            method: 'GET',
                            headers: { 'Accept': 'application/json' }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (!data.submissions || !Array.isArray(data.submissions)) {
                                listDiv.html('<span style="color:red;">❌ Failed to load submissions</span>');
                                return;
                            }

                            if (data.submissions.length === 0) {
                                listDiv.html('<span style="color:gray;">No submissions yet.</span>');
                                return;
                            }

                            let html = '<h4>Your Submissions</h4><ul style="padding-left:1.2em;">';
                            data.submissions.forEach(sub => {
                                const safeSource = $('<div>').text(sub.source_code ?? '').html();
                                html += `
                                    <li style="margin-bottom: 1em;">
                                        <div>
                                            <strong>Status:</strong> ${sub.status} |
                                            <strong>Lang:</strong> ${sub.language ?? '-'} |
                                            <strong>Time:</strong> ${sub.time ?? '-'} |
                                            <strong>Memory:</strong> ${sub.memory ?? '-'}
                                            <button type="button" class="toggle-source" data-id="${sub.submission_id}">show code</button>
                                        </div>
                                        <pre id="source-${sub.submission_id}" class="submission-source" style="display:none;">${safeSource}</pre>
                                    </li>`;
                            });
                            html += '</ul>';
                            listDiv.html(html);
                        })
                        .catch(err => {
                            listDiv.html('<span style="color:red;">❌ Error fetching submissions: ' + err.message + '</span>');
                        });
                            button.text('Hide Submissions');
                        } else {
                            listDiv.empty();
                            button.text('Show Submissions');
                        }
                    });

                    // 🔁 Toggle to show/hide source code
                    if (!window.toggleSourceInitialized) {
                        $(document).on('click', '.toggle-source', function () {
                            const id = $(this).data('id');
                            const block = $('#source-' + id);
                            block.slideToggle(200);

                            const btn = $(this);
                            btn.text(btn.text() === 'show code' ? 'Hide code' : 'show code');
                        });
                        window.toggleSourceInitialized = true;
                    }

                } else if (attemptCount >= maxAttempts) {
                    clearInterval(waitForElement);
                }
            }, 200);
        }
    };
});
/*
let html = '<h4>Your Submissions</h4><ul style="padding-left:1.2em;">';
data.submissions.forEach(sub => {
    const safeSource = $('<div>').text(sub.source_code ?? '').html();
    html += 
        <li style="margin-bottom: 1em;">
            <div>
                <strong>Status:</strong> ${sub.status} |
                <strong>Lang:</strong> ${sub.language ?? '-'} |
                <strong>Time:</strong> ${sub.time ?? '-'} |
                <strong>Memory:</strong> ${sub.memory ?? '-'}
                <button type="button" class="toggle-source" data-id="${sub.submission_id}">show code</button>
            </div>
            <pre id="source-${sub.submission_id}" class="submission-source" style="display:none;">${safeSource}</pre>
        </li>;
});
html += '</ul>';
listDiv.html(html);
*/
