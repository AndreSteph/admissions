// main.dart
import 'package:app/a_level_screen.dart';
import 'package:app/course_suggest_screen.dart';
import 'package:app/o_level_screen.dart';
import 'package:app/verification_screen.dart';
import 'package:flutter/material.dart';
import 'splash_screen.dart'; // Make sure to import the splash screen
import 'login_screen.dart'; // And the login screen
import 'register_screen.dart'; // And the registration screen
import 'university_screen.dart'; // And the university selection screen
void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'University Course Calculator',
      theme: ThemeData(
        fontFamily: 'Nunito',
        primarySwatch: Colors.blue,
        visualDensity: VisualDensity.adaptivePlatformDensity,
      ),
      initialRoute: '/',
      routes: {
        '/': (context) => const SplashScreen(),
        '/login': (context) => const LoginScreen(),
        '/register': (context) => const RegistrationScreen(),
        '/verify': (context) => const VerificationScreen(),
        '/home': (context) => const UniversitySelectionScreen(),
        '/olevel': (context) => const GradeInputScreen(),
        '/alevel': (context) => const SubjectGradeSelectionScreen(),
        '/courses': (context) => CourseListScreen(courses: const [],),
      },
    );
  }
}
