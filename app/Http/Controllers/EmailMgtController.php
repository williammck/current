<?php namespace App\Http\Controllers;

use App\Classes\cPanelHelper;
use Illuminate\Http\Request;
use App\User;
use App\Facility;
use App\Classes\RoleHelper;
use App\Classes\EmailHelper;
use Auth;
use App\Classes\Helper;
use Illuminate\Support\Facades\Input;

class EmailMgtController extends Controller
{
    public function getIndex() {
        return redirect('/mgt/mail/conf');
    }

    public function getConfig() {
        return redirect("/mgt/mail/account");
    }

    public function getAccount() {
        if (!Auth::check()) abort(401);
        return view('mgt.mail.email');
    }

    public function postConfig() {
        if (!Auth::check()) abort(401);

        $role = Auth::user()->getPrimaryRole();
        $fac = Auth::user()->facility;

        if ($fac == "ZHQ")
            $email = "zhq$role@vatusa.net";
        else
            $email = "$fac-$role@vatusa.net";

        $type = (int)cPanelHelper::getType($email);
        $reqType = (int)Input::get("type");
        $reqDest = Input::get("dest");
        $reqPassword = Input::get("password");


        // Full Account
        if ($reqType == 0) {
            if ($type == 0) {
                // Full Account -> Full Account
                cPanelHelper::emailChangePassword($email, $reqPassword);
                return redirect('/mgt/mail/conf')->with('success', 'Successfully changed your password');
            }
            // Forward -> Full Account
            cPanelHelper::forwardDelete($email);
            cPanelHelper::emailCreate($email, $reqPassword);
            return redirect('/mgt/mail/conf')->with('success', 'Deleted forwarder and created email account');
        }
        // Forward
        if ($type == 1) {
            // Forward -> Forward
            cPanelHelper::forwardDelete($email);
            cPanelHelper::forwardCreate($email, $reqDest);
            return redirect('/mgt/mail/conf')->with('success', 'Changed forward address');
        } else {
            // Full Account -> Forward
            cPanelHelper::emailDelete($email);
            cPanelHelper::forwardCreate($email, $reqDest);
            return redirect('/mgt/mail/conf')->with('success', 'Changed full account to forward');
        }
    }
    
    public function getType($user) {
        $data = cPanelHelper::getType("$user@vatusa.net");
        var_dump($data);
        return;
    }

    public function getIndividual($cid) {
        return view('mgt.mail.broadcast', ['cid' => $cid]);
    }

    public function getBroadcast($cid = null) {
        if (!Auth::check() || !RoleHelper::isFacilityStaff()) abort(401);
        return view('mgt.mail.broadcast', ['cid' => $cid]);
    }

    public function postBroadcast(Request $request) {
        if (!Auth::check() || !RoleHelper::isFacilityStaff()) abort(401);
        $rcpts = $request->recipients;
        $subj = $request->subject;
        $msg = $request->email;
        $single = $request->single;

        if((empty($rcpts) && empty($single)) || empty($subj) || empty($msg))
            return redirect("/mgt/mail")->with('error', 'All fields are required.');

        // Send to single person.
        if (empty($rcpts) && !empty($single)) {
            $email = Helper::emailFromCID($single);
            EmailHelper::sendEmailFrom($email, Auth::user()->email, Auth::user()->fname . " " . Auth::user()->lname, $subj, 'emails.mass', array('msg' => nl2br(strip_tags($msg, '<b><i>')), 'init' => Auth::user()->fname." ".Auth::user()->lname." (".Auth::user()->cid.")"));
            return redirect("/mgt/mail/$single")->with('success', 'Email sent.');
        } else {
            // Handle Recipients
            // STAFF = All Staff
            // ALL = VATUSA Controllers
            // SRSTAFF = Facility Senior Staff
            // VATUSA = VATUSA Staff
            // INS = Instructional Staff (I1 + I3)
            // Z--/HCF[SJU/GUM] = Facility

            $emails = array();

            if ($rcpts == "STAFF") {
                $rcpts = "Facility Staff";
                foreach (Facility::get() as $f) {
                    if ($f->atm) $emails[] = $f->id . "-atm@vatusa.net";
                    if ($f->datm) $emails[] = $f->id . "-datm@vatusa.net";
                    if ($f->ta) $emails[] = $f->id . "-ta@vatusa.net";
                    if ($f->ec) $emails[] = $f->id . "-ec@vatusa.net";
                    if ($f->fe) $emails[] = $f->id . "-fe@vatusa.net";
                    if ($f->wm) $emails[] = $f->id . "-wm@vatusa.net";
                }
            } elseif ($rcpts == "ALL") {
                $rcpts = "VATUSA";
                foreach (User::where('facility', 'NOT LIKE', 'ZZN')->where('facility', 'NOT LIKE', 'ZAE')->get() as $u) {
                    if ($u->email) $emails[] = $u->email;
                }
            } elseif ($rcpts == "SRSTAFF") {
                $rcpts = "Facility Senior Staff";
                foreach (Facility::get() as $f) {
                    if ($f->atm) $emails[] = $f->id . "-atm@vatusa.net";
                    if ($f->datm) $emails[] = $f->id . "-datm@vatusa.net";
                    if ($f->ta) $emails[] = $f->id . "-ta@vatusa.net";
                }
            } elseif ($rcpts == "DRCTR") {
                $rcpts = "Facility ATM/DATM";
                foreach (Facility::get() as $f) {
                    $emails[] = $f->id . "-atm@vatusa.net";
                    $emails[] = $f->id . "-datm@vatusa.net";
                }
            } elseif ($rcpts == "WM") {
                $rcpts = "Facility Webmasters";
                foreach (Facility::get() as $f) {
                    $emails[] = $f->id . "-wm@vatusa.net";
                }
            } elseif ($rcpts == "VATUSA") {
                $rcpts = "VATUSA Staff";
                foreach (\App\Role::where('facility', 'ZHQ')->get() as $f) {
                    $emails[] = Helper::emailFromCID($f->cid);
                }
            } elseif ($rcpts == "INS") {
                $rcpts = "Instructional staff";
                foreach (User::get() as $u) {
                    if ($u->rating >= Helper::ratingIntFromShort("I1") && $u->email) $emails[] = $u->email;
                }
            } elseif ($rcpts == "ACE") {
                $rcpts = "ACE Team Members";
                foreach (\App\Role::where('facility', 'ZHQ')->where('role', 'ACE')->get() as $f) {
                    $emails[] = Helper::emailFromCID($f->cid);
                }
            } else {
                foreach (User::where('facility', $rcpts)->get() as $u) {
                    $emails[] = $u->email;
                }
            }
            \App\Classes\EmailHelper::sendEmailBCC(Auth::user()->email, Auth::user()->fname . " " . Auth::user()->lname, $emails, $subj, 'emails.mass', array('msg' => nl2br(strip_tags($msg, '<b><i>')), 'init' => Auth::user()->fname . " " . Auth::user()->lname . " (" . Auth::user()->cid . ")"));
        }
        return redirect("/mgt/mail")->with('success', 'Email sent.');
    }

    public function getWelcome() {
        if (!RoleHelper::isFacilitySeniorStaff()) abort(401);
        
        $fac = Facility::find(\Auth::user()->facility);
        return view('mgt.mail.welcome', ['welcome' => $fac->welcome_text]);
    }

    public function postWelcome(Request $request) {
        if (!RoleHelper::isFacilitySeniorStaff()) abort(401);

        $fac = Facility::find(\Auth::user()->facility);
        $fac->welcome_text = $request->input("welcome");
        $fac->save();
        return redirect("/mgt/mail/welcome")->with("success","Welcome email set.");
    }

    public function getTemplates() {
        if (!RoleHelper::isFacilitySeniorStaff()) abort(401);

        return view('mgt.mail.templatelist');
    }

    public function getTemplateAction($template, $action) {
        if (!RoleHelper::isFacilitySeniorStaff()) abort(401);

        $fac = Auth::user()->facility;

        if ($action == "delete") {
            if (!RoleHelper::isFacilitySeniorStaff()) abort(401);

            if (!in_array($template, ["examassigned","exampassed","examfailed"])) { return redirect("/mgt/mail/template")->with("error", "Invalid template"); }

            if(view()->exists("emails.facility.$fac.$template")) {
                unlink(base_path() . "/resources/views/emails/facility/$fac/$template.blade.php");
            }

            return redirect("/mgt/mail/template")->with("success", "Template set back to VATUSA default");
        }

        if ($action == "edit") {
            if ($template == "examassigned") {
                $variables = [
                    "student_name - The student's full name",
                    "exam_name - The name of the exam",
                    "instructor_name - Name of instructor who assigned the exam",
                    "end_date - Date and time the exam will expire if not taken",
                    "cbt_required - set to 1 if a CBT is required, otherwise 0",
                    "cbt_facility - if cbt_required, set to the name of the facility the CBT Block is under",
                    "cbt_block_name - name of CBT Block",
                    "cbt_block - ID number of CBT Block for linking"
                ];
                $mastertemplate = "exam/assign.blade.php";
                $name = "Exam Assigned";
            } elseif ($template == "exampassed") {
                $variables = [
                    "student_name - Name of student",
                    "exam_name - Name of exam",
                    "correct - Number of questions correct",
                    "possible - Number of questions on exam",
                    "score - Percentage score",
                    "reassign - Set to number of days until auto reassignment, or 0 if not auto-reassign"
                ];
                $mastertemplate = "exam/passed.blade.php";
                $name = "Exam Passed";
            } elseif ($template == "examfailed") {
                $variables = [
                    "student_name - Name of student",
                    "exam_name - Name of exam",
                    "correct - Number of questions correct",
                    "possible - Number of questions on exam",
                    "score - Percentage score"
                ];
                $mastertemplate = "exam/failed.blade.php";
                $name = "Exam Failed";
            } else {
                redirect("/mgt/mail/template")->with("error", "Invalid template");
            }

            if (!file_exists(base_path() . "/resources/views/emails/facility/$fac"))
                mkdir(base_path() . "/resources/views/emails/facility/$fac");

            if(view()->exists("emails.facility.$fac.$template")) {
                $data = file_get_contents(base_path() . "/resources/views/emails/facility/$fac/$template.blade.php");
            } else {
                $data = file_get_contents(base_path() . "/resources/views/emails/$mastertemplate");
            }

            return view('mgt.mail.templateedit', ["template" => $template, "data" => $data, "variables" => $variables, "name" => $name]);
        }
    }

    public function postTemplate(Request $request, $template)
    {
        if (!RoleHelper::isFacilitySeniorStaff()) abort(401);

        if (!in_array($template, ["examassigned","exampassed","examfailed"])) { return redirect("/mgt/mail/template")->with("error", "Invalid template"); }

        $data = $request->template;
        $data = preg_replace(array('/<(\?|\%)\=?(php)?/', '/(\%|\?)>/'), array('',''), $data);
        $fac = Auth::user()->facility;

        if (!file_exists(base_path() . "/resources/views/emails/facility/$fac"))
            mkdir(base_path() . "/resources/views/emails/facility/$fac");

        file_put_contents(base_path() . "/resources/views/emails/facility/$fac/$template.blade.php", $data);

        return redirect("/mgt/mail/template")->with("success", "Saved.");
    }
}
