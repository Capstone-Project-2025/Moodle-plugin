# Introduction
This is the "random0617-standard" branch for Moodle version 3.11.18, website is called "OurMoodleSiteStandard", used in the "regular CS program" (Vietnamese: chương trình đại trà).

In addition to the installation instructions below, `index.php` and `apirequest.php` files have been carried over from the original branch.

# Instructions

Install these two Moodle versions as used by our school:
- version 4.0.12 (Build: 20231211) 
- version 3.11.18 (Build: 20231211)

My conversation with ChatGPT: https://chatgpt.com/share/685020fe-38cc-8003-9a54-a7e9cbaf4d10

To install both of them, you need to do the following:
- Install xampp-portable-windows-x64-7.4.33-0-VC15-installer.exe (XAMPP v7.4.33) from https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/7.4.33/, which supports both Moodle versions above. Then put the XAMPP 7.4 Control Panel on the desktop screen as a shortcut to not be confused with the incorrect version of XAMPP (see ChatGPT conversation above for instructions). Assume that the installed xampp folder is named xampp74 (the default xampp is the incorrect version).
- Install the zip files of Moodle version 4.0.12 and 3.11.18 from this link: https://download.moodle.org/releases/legacy/
- Unzip the installed folders. On my computer, I renamed v4.0.12 "moodle" folder to OurMoodleSiteAPCS (chương trình đề án) and renamed v3.11.18 folder to OurMoodleSiteStandard (chương trình chuẩn).
- Place these two folders in xampp74/htdocs/.
- Go to http://localhost/phpmyadmin, then create a database named moodle311; create another named moodle40.
- Install the two Moodle websites by going to localhost/OurMoodleSiteAPCS and localhost/OurMoodleSiteStandard. Keep default all options except: database name as stated above (separate for each website, moodle311 and moodle40), username root, password empty. Data directory: \xampp74\moodledata311 and xampp74\moodledata40 respectively.