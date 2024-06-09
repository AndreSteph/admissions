// course_list_screen.dart
import 'package:flutter/material.dart';

class CourseListScreen extends StatelessWidget {
    final List<String> courses;

  // Constructor that requires a 'courses' list
  CourseListScreen({Key? key, required this.courses}) : super(key: key);

  // final List<String> courses = ["BAED", "BIT", "BMS", "BCS"]; // List of courses
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            children: [
              SizedBox(
                  height: MediaQuery.of(context).size.height *
                      0.1), // Space from the top
              const Text(
                'Available Courses',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 30),
              ListView.builder(
                shrinkWrap:
                    true, // Use this to fit ListView inside a SingleChildScrollView
                physics:
                    const NeverScrollableScrollPhysics(), // Disables scrolling of ListView
                itemCount: courses.length,
                itemBuilder: (context, index) {
                  return Card(
                    child: ListTile(
                      title: Text(courses[index]),
                      onTap: () {
                        // Action when a course is tapped, if necessary
                        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                            content: Text('Selected ${courses[index]}')));
                      },
                    ),
                  );
                },
              ),
                          const SizedBox(height: 30),
              ElevatedButton(
                onPressed: () {
                  Navigator.pushNamed(context, '/home');

                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.black,
                  foregroundColor: Colors.white,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 50, vertical: 20),
                ),
                child: const Text('Back to Home'),
              ),
            
            ],
          ),
        ),
      ),
    );
  }
}
