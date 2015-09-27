<?php
/* @author  Armande Bayanes
 * */

$engine = new QabanaTemplateEngine('home.html', 'variables.txt', array('VAR'));
$engine->render();

class QabanaTemplateEngine {

    private $body = '';
    private $variables = array();
    private $escape_variables = array();

    function __construct($template, $data, $ev = array()) {

        $this->check_file($template);
        $this->check_file($data);

        $this->body = file_get_contents($template);
        $this->escape_variables = (array) $ev;

        $this->getVariables($data);
    }

    function render() {

        $this->renderForLoop();
        $this->renderSubTemplate();
        $this->renderVars();

        // Declare variables.
        foreach($this->variables['keys'] as $key => $value) {

            $$value = $this->variables['values'][$key];
        }

        eval("?> $this->body");
    }

    private function check_file($file) {

        if(! file_exists($file)) {

            exit('ERROR: ' . $file . ' does not exists.');
        }
    }

    private function getForLoop() {

        // Find (for) loop.
        // $loop_matches[0] - Index 0 will be the text that matched the full pattern.
        // $loop_matches[1] ... [n] - From index 1 will have the captured parenthesized sub-pattern, and so on.
        preg_match_all("/" . preg_quote('--for ++') . '(.*)' . preg_quote('++ as ') . '(.*)' . preg_quote('--') . "/", $this->body, $loop_matches);

        if(! empty($loop_matches)) {

            $final = array();

            if(($count = count($loop_matches)) > 0) {

                for($x=1; $x<$count; $x++) {

                    foreach($loop_matches[$x] as $key => $value) {

                        if(! isset($final[$key])) {
                            $final[$key] = $loop_matches[0][$key];
                        }

                        $final[$key] = str_replace('++' . $value . '++', '$' . $value, $final[$key]);
                        // Apply foreach loop but also validates variable first.
                        $final[$key] = str_replace('--for ', '<?php if(isset($' . $value . ')) { foreach( ', $final[$key]);
                        $final[$key] = str_replace($value . '--', '$' . $value. ' ) {?>', $final[$key]);
                    }
                }
            }
        }

        return array($loop_matches, $final);
    }

    private function renderForLoop() {

        list($loop_matches, $final) = $this->getForLoop();

        if(! empty($final)) {

            foreach($loop_matches[0] as $index => $loop) {

                $this->body = str_replace($loop, $final[$index], $this->body);
            }

            $this->body = str_replace('--endfor--', '<?php } }?>', $this->body); // 2 closing tags for validation and loop ends.
        }
    }

    private function renderVars() {

        // Get all variables present in the template.
        preg_match_all('/' . preg_quote('++') . '([A-Z0-9]+)' . preg_quote('++') . '/', $this->body, $variables);
        if(empty($variables)) return;

        $variables = $variables[1];
        $variables = array_merge(array_unique($variables));

        // Escape specific variable from the collection of variables.
        $remove = array_merge(array_diff($this->escape_variables, $this->variables['keys']));

        // Get only the replaceable variables.
        $variables = array_merge(array_diff($variables, $remove));

        if(($total = count($variables)) > 0) {

            for($x=0; $x<$total; $x++) {

                $this->body = str_replace('++' . $variables[$x] . '++', '<?php echo $'. $variables[$x] . '?>', $this->body);
            }
        }
    }

    private function renderSubTemplate() {

        preg_match_all('/' . preg_quote('--include ') . '(.*)' . preg_quote(' with ') . '(.*)'. preg_quote('--') . '/', $this->body, $include_matches);

        if(! empty($include_matches)) {

            foreach($include_matches[0] as $key => $value) {

                if(file_exists($include_matches[1][$key])) {

                    $include_file = file_get_contents($include_matches[1][$key]); // Get content of document/file.
                    $this->body = str_replace($value, trim($include_file), $this->body);
                }
            }
        }
    }

    // Get variables (from variables.txt)
    private function getVariables($data) {

        $data = file($data);
        $keys = $values = array();

        if(($total = count($data)) > 0) {

            for($x=0; $x<$total; $x++) {

                $data[$x] = trim($data[$x]);

                // Break keys from values by the first equal sign, maintaining values with equal signs.
                $first_equal_sign = strpos($data[$x], '=');
                $keys[$x] = substr($data[$x], 0, $first_equal_sign);
                $values[$x] = substr($data[$x], $first_equal_sign + 1);

                if(substr_count($values[$x], ',')) {

                    // Variable that has multiple values so convert in array form.
                    $values[$x] = explode(',', $values[$x]);

                } else $values[$x] = trim($values[$x]); // Single string value.
            }
        }

        $this->variables['keys'] = $keys;
        $this->variables['values'] = $values;
    }
}