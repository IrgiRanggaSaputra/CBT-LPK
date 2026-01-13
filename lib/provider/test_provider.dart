import '../services/api_service.dart';
import '../models/test_model.dart';
import '../models/question_model.dart';

class TestProvider {
  final _api = ApiService();

  Future<List<TestModel>> fetchTests() async {
    final data = await _api.getTests();
    return data.map((e) => TestModel.fromJson(e)).toList();
  }

  Future<List<QuestionModel>> fetchQuestions(String pesertaTesId) async {
    final response = await _api.getQuestions(pesertaTesId);
    final soalList = response['soal'] as List? ?? [];
    return soalList
        .map((e) => QuestionModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<Map<String, dynamic>> getTestDetail(String jadwalId) async {
    return await _api.getTestDetail(jadwalId);
  }

  Future<Map<String, dynamic>> startTest(String jadwalId) async {
    return await _api.startTest(jadwalId);
  }

  Future<Map<String, dynamic>> submitTest(String pesertaTesId) async {
    return await _api.submitTest(pesertaTesId);
  }

  Future<Map<String, dynamic>> getResult(String jadwalId) async {
    return await _api.getTestResult(jadwalId);
  }

  Future<List<dynamic>> getHistory() async {
    return await _api.getTestHistory();
  }
}
