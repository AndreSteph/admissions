// university_selection_screen.dart
// ignore_for_file: use_build_context_synchronously

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'constants.dart';
import 'package:shared_preferences/shared_preferences.dart';

class UniversitySelectionScreen extends StatefulWidget {
  const UniversitySelectionScreen({Key? key}) : super(key: key);

  @override
  _UniversitySelectionScreenState createState() =>
      _UniversitySelectionScreenState();
}

class _UniversitySelectionScreenState extends State<UniversitySelectionScreen> {
List<String> universities = [];
  List<String> universityIds = [];

  Future<void> fetchUniversities() async {
    try {
      final response = await http.get(Uri.parse('${Constants.baseUrl}/admin/university'));
      if (response.statusCode == 200) {
        final jsonData = json.decode(response.body);
        final List<dynamic> universitiesData = jsonData['data']['universities'];
        setState(() {
          universities = universitiesData.map((uni) => uni['title']).toList().cast<String>();
          universityIds = universitiesData.map((uni) => uni['id'].toString()).toList().cast<String>();
        });
      } else {
        throw Exception('Failed to load universities');
      }
    } catch (error) {
      print('Error fetching universities: $error');
    }
  }

  Future<void> _setUniversityId(String universityId) async {
    try {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      await prefs.setString('universityId', universityId);
      print('University ID set: $universityId');
    } catch (error) {
      print('Error setting university ID: $error');
    }
  }

  Future<String?> _getUniversityId() async {
    try {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      return prefs.getString('universityId');
    } catch (error) {
      print('Error getting university ID: $error');
      return null;
    }
  }

  @override
  void initState() {
    super.initState();
    fetchUniversities();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: universities.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                SizedBox(
                  height: MediaQuery.of(context).size.height * 0.1,
                ),
                const Padding(
                  padding: EdgeInsets.all(16.0),
                  child: Text(
                    'Select University',
                    style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                  ),
                ),
                Expanded(
                  child: GridView.builder(
                    padding: const EdgeInsets.all(8.0),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      crossAxisSpacing: 10,
                      mainAxisSpacing: 10,
                      childAspectRatio: 3 / 2,
                    ),
                    itemCount: universities.length,
                    itemBuilder: (context, index) {
                      return Card(
                        child: Center(
                          child: ListTile(
                            title: Text(
                              universities[index],
                              textAlign: TextAlign.center,
                            ),
                            onTap: () async {
                              await _setUniversityId(universityIds[index]);
                              final selectedUniversityId = await _getUniversityId();
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(
                                    'Selected ${universities[index]}, ID: $selectedUniversityId',
                                  ),
                                ),
                              );
                              // Navigate to olevel page or wherever you need
                              Navigator.pushNamed(context, '/olevel');
                            },
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
    );
  }
}
