// grade_input_screen.dart
// ignore_for_file: use_build_context_synchronously

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class GradeInputScreen extends StatefulWidget {
  const GradeInputScreen({Key? key}) : super(key: key);

  @override
  _GradeInputScreenState createState() => _GradeInputScreenState();
}

class _GradeInputScreenState extends State<GradeInputScreen> {
  final _formKey = GlobalKey<FormState>();

  // These will hold the entered values
  String _numDs = '';
  String _numCs = '';
  String _numPs = '';
  String _numFs = '';

  void _submit() async {
    if (_formKey.currentState?.validate() == true) {
      _formKey.currentState?.save();

      // Convert the saved string values to integers
      int numDs = int.parse(_numDs);
      int numCs = int.parse(_numCs);
      int numPs = int.parse(_numPs);
      int numFs = int.parse(_numFs);

      // Calculate the total of all grades
      int totalGrades = numDs + numCs + numPs + numFs;

      // Check if the total exceeds 10
      if (totalGrades > 10) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text(
                  'The total number of grades cannot exceed 10. Please adjust your entries.')),
        );
        return;
      }

      // Store the values in SharedPreferences
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt('numDs', numDs);
      await prefs.setInt('numCs', numCs);
      await prefs.setInt('numPs', numPs);
      await prefs.setInt('numFs', numFs);

      // Navigate to the next screen
      Navigator.pushNamed(context, '/alevel');
    }
  }


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                const SizedBox(height: 150),
                const Text(
                  'Enter Grades O-Level Information',
                  style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 20),
                TextFormField(
                  decoration: const InputDecoration(
                    labelText: 'Enter number of Ds',
                    border: OutlineInputBorder(),
                  ),
                  keyboardType: TextInputType.number,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter a valid number';
                    }
                    return null;
                  },
                  onSaved: (value) => _numDs = value!,
                ),
                const SizedBox(height: 10),
                TextFormField(
                  decoration: const InputDecoration(
                    labelText: 'Enter number of Cs',
                    border: OutlineInputBorder(),
                  ),
                  keyboardType: TextInputType.number,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter a valid number';
                    }
                    return null;
                  },
                  onSaved: (value) => _numCs = value!,
                ),
                const SizedBox(height: 10),
                TextFormField(
                  decoration: const InputDecoration(
                    labelText: 'Enter number of Ps',
                    border: OutlineInputBorder(),
                  ),
                  keyboardType: TextInputType.number,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter a valid number';
                    }
                    return null;
                  },
                  onSaved: (value) => _numPs = value!,
                ),
                const SizedBox(height: 10),
                TextFormField(
                  decoration: const InputDecoration(
                    labelText: 'Enter number of Fs',
                    border: OutlineInputBorder(),
                  ),
                  keyboardType: TextInputType.number,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter a valid number';
                    }
                    return null;
                  },
                  onSaved: (value) => _numFs = value!,
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: (){
                  // Navigator.pushNamed(context, '/alevel');
                  _submit();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 50, vertical: 20),
                  ),
                  child: const Text('Next A-Level'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
