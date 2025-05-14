<?php
defined('MOODLE_INTERNAL') || die();

class qtype_programming_renderer extends qtype_renderer {

    // === Appel API pour récupérer les infos du problème ===
    private function fetch_problem_data($problemcode) {
        $apiurl = 'http://139.59.105.152';
        $username = 'admin';
        $password = 'admin';

        // 1. Authentification
        $curl = curl_init("$apiurl/api/token/");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password
        ]));
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);
        $token = $data['access'] ?? null;

        if (!$token) {
            return null;
        }

        // 2. Récupération des données du problème
        $curl = curl_init("$apiurl/api/v2/problem/$problemcode");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token"
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $problem = json_decode($response, true);
        return $problem['data']['object'] ?? null;
    }

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        static $alreadydisplayed = false;

        $question = $qa->get_question();
        $codeOfProblem = $question->problemcode ?? '';
        $questiontext = $question->questiontext ?? '';

        $output = '';

        if (!$alreadydisplayed && !empty($codeOfProblem)) {
            $apidata = $this->fetch_problem_data($codeOfProblem);

            $name = $apidata['name'] ?? '(no name)';
            $description = $apidata['description'] ?? '(no description)';

            $output .= html_writer::div(
                "<h3>Programming Problem</h3>" .
                "<strong>Code:</strong> " . s($codeOfProblem) . "<br>" .
                "<strong>Name:</strong> " . s($name) . "<br><br>" .
                "<strong>Description:</strong><br>" .
                format_text($description, FORMAT_MARKDOWN),
                'global-problem-info',
                ['style' => 'border: 2px solid blue; padding: 10px; margin-bottom: 20px; background: #eef;']
            );

            $alreadydisplayed = true;
        }

        // === Zone de réponse ===
        $inputname = $qa->get_qt_field_name('answer');
        $response = $qa->get_last_qt_data();
        $answer = $response['answer'] ?? '';

        $output .= html_writer::tag('textarea', s($answer), [
            'name' => $inputname,
            'rows' => 10,
            'cols' => 80,
            'style' => 'width: 100%; margin-top: 15px;',
        ]);

        return $output;
    }
}
