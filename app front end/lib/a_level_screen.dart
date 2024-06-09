// subject_grade_selection_screen.dart
// ignore_for_file: use_build_context_synchronously

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert'; // For using json.decode
import 'constants.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'course_suggest_screen.dart';
class SubjectGradeSelectionScreen extends StatefulWidget {
  const SubjectGradeSelectionScreen({Key? key}) : super(key: key);

  @override
  _SubjectGradeSelectionScreenState createState() =>
      _SubjectGradeSelectionScreenState();
}

class _SubjectGradeSelectionScreenState
    extends State<SubjectGradeSelectionScreen> {
  List<String> allSubjects = [];

  Future<void> fetchSubjects() async {
    const String url = '${Constants.baseUrl}/admin/subjects';
    try {
      final response = await http.get(Uri.parse(url));

      if (response.statusCode == 200) {
        var data = jsonDecode(response.body);

        // Check for success flag and statusCode
        if (data['success'] == true && data['statusCode'] == 200) {
          List<dynamic> subjects = data['data']['subjects'];
          // print(subjects);
          setState(() {
            allSubjects =
                subjects.map((subject) => subject['title'].toString()).toList();
          });
        } else {
          // Handle the case where the API returns an error
          throw Exception('Failed to load subjects');
        }
      } else {
        // Handle the case where the server did not return a 200 OK response
        throw Exception('Failed to load subjects');
      }
    } catch (e) {
      // Handle exceptions by printing or displaying them
      print('Error fetching subjects: $e');
    }
  }

  bool isLoading = false;

  void setLoading(bool loading) {
    setState(() {
      isLoading = loading;
    });
  }

  @override
  void initState() {
    super.initState();
    fetchSubjects();
  }

  final List<String> grades = ["A", "B", "C", "D", "E", "F"];
  String subSubjectGrade = "O";
  String generalPaperGrade = "O";
  List<String?> selectedSubjects =
      List.filled(3, null); // Stores current selections
  Map<String, String?> selectedGrades = {}; // Grades for selected subjects

Future<List<String>> submitGrades() async {
    final prefs = await SharedPreferences.getInstance();
    final universityId = prefs.getString('universityId');
    final numDs = prefs.getInt('numDs') ?? 0;
    final numCs = prefs.getInt('numCs') ?? 0;
    final numPs = prefs.getInt('numPs') ?? 0;
    final numFs = prefs.getInt('numFs') ?? 0;

    final Map<String, dynamic> payload = {
      'universityId': universityId,
      'grades': {
        'numDs': numDs,
        'numCs': numCs,
        'numPs': numPs,
        'numFs': numFs,
        'selectedSubjects': selectedGrades,
        'subSubjectGrade': subSubjectGrade,
        'generalPaperGrade': generalPaperGrade
      }
    };

    final String jsonPayload = jsonEncode(payload);
    final response = await http.post(
      Uri.parse('${Constants.baseUrl}/client/submitGrades'),
      headers: {"Content-Type": "application/json"},
      body: jsonPayload,
    );

    if (response.statusCode == 201 && response.body.isNotEmpty) {
      final responseData = jsonDecode(response.body);
      if (responseData != null && responseData['data'] != null) {
        // Extracting course titles from the 'data' array
        final List<String> courses = List<String>.from(responseData['data']
            .map((course) => course['course_title'].toString()));
        return courses;
      } else {
        throw Exception('No courses data found');
      }
    } else {
      throw Exception('Failed to submit grades or bad response from server');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            children: [
              const SizedBox(height: 30),
              const Text(
                'Select A-Level Subjects and Grades',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 30),
              ...List.generate(
                  3,
                  (index) => Column(
                        children: [
                          DropdownButtonFormField<String>(
                            value: selectedSubjects[index],
                            decoration: InputDecoration(
                                labelText: "Select Subject ${index + 1}"),
                            onChanged: (newValue) {
                              setState(() {
                                selectedSubjects[index] = newValue;
                                if (!selectedGrades.containsKey(newValue)) {
                                  selectedGrades[newValue!] = null;
                                }
                              });
                            },
                            items: allSubjects
                                .where((subject) =>
                                    !selectedSubjects.contains(subject) ||
                                    subject == selectedSubjects[index])
                                .map<DropdownMenuItem<String>>(
                                    (String subject) {
                              return DropdownMenuItem<String>(
                                value: subject,
                                child: Text(subject),
                              );
                            }).toList(),
                          ),
                          if (selectedSubjects[index] !=
                              null) // Only show grade dropdown if a subject has been selected
                            DropdownButtonFormField<String>(
                              value: selectedGrades[selectedSubjects[index]],
                              decoration: InputDecoration(
                                  labelText:
                                      "Select Grade for ${selectedSubjects[index]}"),
                              onChanged: (newValue) {
                                setState(() {
                                  selectedGrades[selectedSubjects[index]!] =
                                      newValue;
                                });
                              },
                              items: grades.map<DropdownMenuItem<String>>(
                                  (String grade) {
                                return DropdownMenuItem<String>(
                                  value: grade,
                                  child: Text(grade),
                                );
                              }).toList(),
                            ),
                          const SizedBox(height: 20),
                        ],
                      )),
              const Text(
                'Select Subsidiary Subject and General Paper Grades:',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
              ),
              DropdownButtonFormField<String>(
                value: subSubjectGrade,
                decoration: const InputDecoration(
                    labelText: 'Subsidiary Subject Grade:'),
                onChanged: (newValue) {
                  setState(() {
                    subSubjectGrade = newValue!;
                  });
                },
                items: ['O', 'F'].map<DropdownMenuItem<String>>((String value) {
                  return DropdownMenuItem<String>(
                    value: value,
                    child: Text(value),
                  );
                }).toList(),
              ),
              DropdownButtonFormField<String>(
                value: generalPaperGrade,
                decoration:
                    const InputDecoration(labelText: 'General Paper Grade:'),
                onChanged: (newValue) {
                  setState(() {
                    generalPaperGrade = newValue!;
                  });
                },
                items: ['O', 'F'].map<DropdownMenuItem<String>>((String value) {
                  return DropdownMenuItem<String>(
                    value: value,
                    child: Text(value),
                  );
                }).toList(),
              ),
              const SizedBox(height: 30),
              ElevatedButton(
                onPressed: () async {
                try {
                    setLoading(true); // Show a loading indicator
                    List<String> courses = await submitGrades();
                    setLoading(false); // Hide the loading indicator
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (context) =>
                              CourseListScreen(courses: courses)),
                    );
                  } catch (e) {
                    setLoading(false); // Always ensure to turn off the loader
                    showDialog(
                      context: context,
                      builder: (context) => AlertDialog(
                        title: Text("Error"),
                        content: Text(
                            "Failed to load courses. Please try again.\nError: $e"),
                        actions: [
                          TextButton(
                            child: Text("OK"),
                            onPressed: () =>
                                Navigator.of(context).pop(), // Close the dialog
                          )
                        ],
                      ),
                    );
                  }
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.black,
                  foregroundColor: Colors.white,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 50, vertical: 20),
                ),
                child: const Text('Submit Grades To Get  Course Options'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
